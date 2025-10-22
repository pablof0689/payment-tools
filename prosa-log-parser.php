<?php
declare(strict_types=1);

require __DIR__ . '/includes/template.php';

renderPage('Parser de logs Prosa', static function (): void {
    ?>
    <section class="tool-card prosa-tool">
        <header>
            <h2>Prosa Log Parser</h2>
            <p>Transforma los logs crudos exportados desde Kibana en un resumen listo para analizar referencias,
                PAN enmascarados e importes.</p>
        </header>
        <form class="prosa-form" action="javascript:void(0);">
            <label>
                <span>Entrada (hasta 500 registros JSON)</span>
                <textarea id="prosa-log-input" spellcheck="false" placeholder="Pega aquí los registros completos devueltos por Kibana. La herramienta detectará cada objeto JSON de manera automática."></textarea>
            </label>
            <div class="form-actions">
                <button type="button" id="prosa-process" disabled>Procesar logs</button>
                <button type="button" id="prosa-clear" class="button-secondary">Limpiar</button>
            </div>
        </form>
        <div class="prosa-feedback">
            <p id="prosa-summary" class="helper-text">Aún no se han procesado registros.</p>
        </div>
        <label class="prosa-output">
            <span>Resultado consolidado (JSON)</span>
            <textarea id="prosa-json-output" readonly placeholder="El resultado en formato JSON aparecerá aquí una vez procesados los logs."></textarea>
        </label>
        <div class="prosa-table-wrapper">
            <table class="prosa-table">
                <thead>
                <tr>
                    <th>#</th>
                    <th>Referencia</th>
                    <th>Masked PAN</th>
                    <th>Marca</th>
                    <th>Monto</th>
                    <th>Moneda</th>
                    <th>Merchant</th>
                    <th>Endpoint</th>
                </tr>
                </thead>
                <tbody id="prosa-table-body">
                <tr>
                    <td colspan="8">Aún no hay datos procesados.</td>
                </tr>
                </tbody>
            </table>
        </div>
        <details id="prosa-error-details" class="prosa-errors" hidden>
            <summary>Registros con errores (<span id="prosa-error-count">0</span>)</summary>
            <ul id="prosa-error-list"></ul>
        </details>
    </section>
    <script>
        (function () {
            const input = document.querySelector('#prosa-log-input');
            const processButton = document.querySelector('#prosa-process');
            const clearButton = document.querySelector('#prosa-clear');
            const summary = document.querySelector('#prosa-summary');
            const output = document.querySelector('#prosa-json-output');
            const tableBody = document.querySelector('#prosa-table-body');
            const errorDetails = document.querySelector('#prosa-error-details');
            const errorCount = document.querySelector('#prosa-error-count');
            const errorList = document.querySelector('#prosa-error-list');

            if (!input || !processButton || !clearButton || !summary || !output || !tableBody || !errorDetails || !errorCount || !errorList) {
                return;
            }

            const emptyTableRow = '<tr><td colspan="8">Aún no hay datos procesados.</td></tr>';

            function escapeHtml(value) {
                return value.replace(/[&<>"']/g, function (character) {
                    switch (character) {
                        case '&':
                            return '&amp;';
                        case '<':
                            return '&lt;';
                        case '>':
                            return '&gt;';
                        case '"':
                            return '&quot;';
                        case '\'':
                            return '&#39;';
                        default:
                            return character;
                    }
                });
            }

            function extractJsonObjects(text) {
                const results = [];
                let current = '';
                let depth = 0;
                let inString = false;
                let isEscaped = false;

                for (let index = 0; index < text.length; index += 1) {
                    const char = text[index];

                    if (depth === 0) {
                        if (char === '{') {
                            current = '{';
                            depth = 1;
                            inString = false;
                            isEscaped = false;
                        }
                        continue;
                    }

                    current += char;

                    if (isEscaped) {
                        isEscaped = false;
                        continue;
                    }

                    if (char === '\\') {
                        isEscaped = true;
                        continue;
                    }

                    if (char === '"') {
                        inString = !inString;
                        continue;
                    }

                    if (inString) {
                        continue;
                    }

                    if (char === '{') {
                        depth += 1;
                    } else if (char === '}') {
                        depth -= 1;
                        if (depth === 0) {
                            results.push(current.trim());
                            current = '';
                        }
                    }
                }

                return results;
            }

            function parseEmbeddedPayload(message) {
                if (typeof message !== 'string' || message.trim() === '') {
                    return { success: false, error: 'El campo "message" está vacío.' };
                }

                const objects = extractJsonObjects(message);

                for (let i = 0; i < objects.length; i += 1) {
                    const segment = objects[i];
                    try {
                        return { success: true, payload: JSON.parse(segment) };
                    } catch (error) {
                        // Intentamos con el siguiente objeto si existiera.
                    }
                }

                return { success: false, error: 'No se detectó un bloque JSON válido en el campo "message".' };
            }

            function getNested(source, path) {
                return path.reduce(function (value, key) {
                    if (value && typeof value === 'object' && key in value) {
                        return value[key];
                    }
                    return undefined;
                }, source);
            }

            function asCleanString(value) {
                if (typeof value === 'string') {
                    return value.trim();
                }
                if (typeof value === 'number') {
                    return String(value);
                }
                return null;
            }

            function extractEndpoint(message) {
                if (typeof message !== 'string') {
                    return null;
                }
                const match = message.match(/ENDPOINT:\s*([^\n]+)/i);
                return match ? match[1].trim() : null;
            }

            function parseLogs(raw) {
                const segments = extractJsonObjects(raw);
                const results = [];
                const errors = [];

                segments.forEach(function (segment, segmentIndex) {
                    try {
                        const base = JSON.parse(segment);
                        const message = typeof base.message === 'string' ? base.message : '';
                        const embedded = parseEmbeddedPayload(message);

                        if (!embedded.success) {
                            throw new Error(embedded.error);
                        }

                        const detail = embedded.payload || {};
                        const transaction = detail.transaction && typeof detail.transaction === 'object' ? detail.transaction : {};
                        const totalAmount = transaction.totalAmount && typeof transaction.totalAmount === 'object' ? transaction.totalAmount : {};
                        const merchant = transaction.merchant && typeof transaction.merchant === 'object' ? transaction.merchant : {};
                        const internal = detail.internal && typeof detail.internal === 'object' ? detail.internal : {};
                        const poi = internal.poi && typeof internal.poi === 'object' ? internal.poi : {};

                        let instrument = null;
                        if (detail.paymentData && typeof detail.paymentData === 'object' && Array.isArray(detail.paymentData.instrument) && detail.paymentData.instrument.length > 0) {
                            const candidate = detail.paymentData.instrument[0];
                            if (candidate && typeof candidate === 'object') {
                                instrument = candidate;
                            }
                        }

                        const entry = {
                            registro: segmentIndex + 1,
                            timestamp: asCleanString(base.timestamp),
                            correlationId: asCleanString(getNested(base, ['mdc', 'correlation_id'])) || asCleanString(getNested(base, ['mdc', 'correlationId'])),
                            endpoint: asCleanString(extractEndpoint(message)),
                            referenceId: asCleanString(transaction.referenceId),
                            initiatorTraceId: asCleanString(transaction.initiatorTraceId),
                            transactionType: asCleanString(transaction.transactionType),
                            amount: asCleanString(totalAmount.value),
                            currency: asCleanString(totalAmount.currencyCode),
                            maskedPan: instrument ? asCleanString(instrument.maskedCardNumber) : null,
                            cardBrand: instrument ? asCleanString(instrument.cardBrand) : null,
                            cardProduct: instrument ? asCleanString(instrument.cardProduct) : null,
                            merchantId: asCleanString(merchant.merchantId),
                            merchantName: asCleanString(merchant.name),
                            transactionUuid: asCleanString(internal.transactionUUID),
                            altPoiId: asCleanString(poi.altVfiPoiId),
                            poiId: asCleanString(transaction.poi && typeof transaction.poi === 'object' ? transaction.poi.poiId : null)
                        };

                        results.push(entry);
                    } catch (error) {
                        const reason = error instanceof Error ? error.message : 'Error desconocido';
                        errors.push({ index: segmentIndex + 1, reason });
                    }
                });

                return { segments: segments.length, results, errors };
            }

            function formatCell(value) {
                if (value === null || value === undefined || value === '') {
                    return '<span class="muted-text">—</span>';
                }
                return escapeHtml(String(value));
            }

            function renderTable(rows) {
                if (!Array.isArray(rows) || rows.length === 0) {
                    tableBody.innerHTML = emptyTableRow;
                    return;
                }

                const html = rows.map(function (row) {
                    return '<tr>' +
                        '<td>' + formatCell(row.registro) + '</td>' +
                        '<td>' + formatCell(row.referenceId) + '</td>' +
                        '<td>' + formatCell(row.maskedPan) + '</td>' +
                        '<td>' + formatCell(row.cardBrand) + '</td>' +
                        '<td>' + formatCell(row.amount) + '</td>' +
                        '<td>' + formatCell(row.currency) + '</td>' +
                        '<td>' + formatCell(row.merchantName || row.merchantId) + '</td>' +
                        '<td>' + formatCell(row.endpoint) + '</td>' +
                        '</tr>';
                }).join('');

                tableBody.innerHTML = html;
            }

            function renderSummary(segments, successes, errors) {
                if (segments === 0) {
                    summary.textContent = 'No se detectaron objetos JSON en la entrada.';
                    return;
                }

                const parts = [successes + ' de ' + segments + ' registros procesados correctamente.'];
                if (errors > 0) {
                    parts.push(errors + ' con errores.');
                }
                summary.textContent = parts.join(' ');
            }

            function renderErrors(entries) {
                if (!Array.isArray(entries) || entries.length === 0) {
                    errorDetails.hidden = true;
                    errorDetails.open = false;
                    errorCount.textContent = '0';
                    errorList.innerHTML = '';
                    return;
                }

                errorDetails.hidden = false;
                errorDetails.open = true;
                errorCount.textContent = String(entries.length);
                errorList.innerHTML = entries.map(function (entry) {
                    return '<li>Registro ' + entry.index + ': ' + escapeHtml(entry.reason) + '</li>';
                }).join('');
            }

            function handleProcess() {
                const rawInput = input.value;
                const result = parseLogs(rawInput);

                renderSummary(result.segments, result.results.length, result.errors.length);
                renderTable(result.results);
                renderErrors(result.errors);

                if (result.results.length > 0) {
                    output.value = JSON.stringify(result.results, null, 2);
                } else {
                    output.value = '';
                }
            }

            function handleClear() {
                input.value = '';
                output.value = '';
                summary.textContent = 'Aún no se han procesado registros.';
                tableBody.innerHTML = emptyTableRow;
                renderErrors([]);
                updateButtonState();
            }

            function updateButtonState() {
                processButton.disabled = input.value.trim().length === 0;
            }

            processButton.addEventListener('click', handleProcess);
            clearButton.addEventListener('click', handleClear);
            input.addEventListener('input', updateButtonState);
            updateButtonState();
        })();
    </script>
    <?php
});
