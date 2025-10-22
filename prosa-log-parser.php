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
        <div class="prosa-controls">
            <fieldset class="prosa-columns">
                <legend>Columnas visibles</legend>
                <div id="prosa-column-options" class="prosa-columns-grid"></div>
            </fieldset>
            <div class="prosa-export">
                <span class="prosa-export-label">Exportar resultados</span>
                <button type="button" id="prosa-export-json" class="button-secondary" disabled>Exportar JSON</button>
                <button type="button" id="prosa-export-csv" class="button-secondary" disabled>Exportar CSV</button>
            </div>
        </div>
        <div class="prosa-table-wrapper">
            <table class="prosa-table">
                <thead>
                <tr id="prosa-table-head-row"></tr>
                </thead>
                <tbody id="prosa-table-body">
                <tr>
                    <td>Aún no hay datos procesados.</td>
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
            const table = document.querySelector('.prosa-table');
            const tableBody = document.querySelector('#prosa-table-body');
            const tableHeadRow = document.querySelector('#prosa-table-head-row');
            const columnOptions = document.querySelector('#prosa-column-options');
            const exportJsonButton = document.querySelector('#prosa-export-json');
            const exportCsvButton = document.querySelector('#prosa-export-csv');
            const errorDetails = document.querySelector('#prosa-error-details');
            const errorCount = document.querySelector('#prosa-error-count');
            const errorList = document.querySelector('#prosa-error-list');
            const defaultSummary = 'Aún no se han procesado registros.';

            if (!input || !processButton || !clearButton || !summary || !table || !tableBody || !tableHeadRow || !columnOptions || !exportJsonButton || !exportCsvButton || !errorDetails || !errorCount || !errorList) {
                return;
            }

            const columnDefinitions = [
                { key: 'registro', label: '#' },
                { key: 'timestamp', label: 'Timestamp' },
                { key: 'correlationId', label: 'Correlation ID' },
                { key: 'endpoint', label: 'Endpoint' },
                { key: 'referenceId', label: 'Referencia' },
                { key: 'initiatorTraceId', label: 'Trace ID' },
                { key: 'transactionType', label: 'Tipo de transacción' },
                { key: 'amount', label: 'Monto' },
                { key: 'currency', label: 'Moneda' },
                { key: 'maskedPan', label: 'Masked PAN' },
                { key: 'cardBrand', label: 'Marca' },
                { key: 'cardProduct', label: 'Producto' },
                { key: 'merchantName', label: 'Comercio' },
                { key: 'merchantId', label: 'Merchant ID' },
                { key: 'transactionUuid', label: 'Transaction UUID' },
                { key: 'altPoiId', label: 'Alt POI ID' },
                { key: 'poiId', label: 'POI ID' }
            ];

            const defaultColumnKeys = ['registro', 'referenceId', 'maskedPan', 'cardBrand', 'amount', 'currency', 'merchantName', 'endpoint'];
            const selectedColumns = new Set(defaultColumnKeys);
            let processedRows = [];

            function escapeHtml(value) {
                return String(value).replace(/[&<>"']/g, function (character) {
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

            function sanitizeLines(raw) {
                if (typeof raw !== 'string') {
                    return [];
                }

                const lines = raw.split(/\r?\n/);
                const cleaned = [];

                lines.forEach(function (line) {
                    if (!line) {
                        return;
                    }

                    let sanitizedLine = line.trim();

                    if (sanitizedLine === '' || sanitizedLine.toLowerCase() === 'message') {
                        return;
                    }

                    const hasOuterDoubleQuotes = sanitizedLine.startsWith('"') && sanitizedLine.endsWith('"');
                    const hasOuterSingleQuotes = sanitizedLine.startsWith("'") && sanitizedLine.endsWith("'");
                    const hasOuterQuotes = hasOuterDoubleQuotes || hasOuterSingleQuotes;

                    if (hasOuterQuotes) {
                        sanitizedLine = sanitizedLine.replace(/""/g, '"');
                        sanitizedLine = sanitizedLine.replace(/\\""/g, '"');
                        sanitizedLine = sanitizedLine.replace(/^"+|"+$/g, '');
                        sanitizedLine = sanitizedLine.replace(/^'+|'+$/g, '');
                        sanitizedLine = sanitizedLine.replace(/\\n/g, '');
                        sanitizedLine = sanitizedLine.replace(/,+\s*$/g, '');
                    }

                    sanitizedLine = sanitizedLine.trim();

                    if (sanitizedLine !== '') {
                        cleaned.push(sanitizedLine);
                    }
                });

                return cleaned;
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
                const match = message.match(/ENDPOINT:\s*([^\n\r<{]+)/i);
                return match ? match[1].trim() : null;
            }

            function collectSegments(raw) {
                const sanitizedLines = sanitizeLines(raw);

                if (sanitizedLines.length > 0) {
                    const joined = sanitizedLines.join('\n');
                    const segments = extractJsonObjects(joined);

                    if (segments.length > 0) {
                        return segments;
                    }

                    return sanitizedLines;
                }

                return extractJsonObjects(raw);
            }

            function parseLogs(raw) {
                const segments = collectSegments(raw);
                const results = [];
                const errors = [];

                segments.forEach(function (segment, segmentIndex) {
                    if (typeof segment !== 'string' || segment.trim() === '') {
                        return;
                    }

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

                        const merchantId = asCleanString(merchant.merchantId);
                        const merchantName = asCleanString(merchant.name);

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
                            merchantId,
                            merchantName: merchantName || merchantId,
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

            function getVisibleColumns() {
                return columnDefinitions.filter(function (column) {
                    return selectedColumns.has(column.key);
                });
            }

            function renderColumnOptions() {
                if (!columnOptions) {
                    return;
                }

                columnOptions.innerHTML = '';
                const fragment = document.createDocumentFragment();

                columnDefinitions.forEach(function (column) {
                    const optionLabel = document.createElement('label');
                    optionLabel.className = 'prosa-column-option';

                    const checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.value = column.key;
                    checkbox.checked = selectedColumns.has(column.key);
                    checkbox.addEventListener('change', function (event) {
                        handleColumnToggle(column.key, checkbox.checked, event.target);
                    });

                    const text = document.createElement('span');
                    text.textContent = column.label;

                    optionLabel.appendChild(checkbox);
                    optionLabel.appendChild(text);
                    fragment.appendChild(optionLabel);
                });

                columnOptions.appendChild(fragment);
            }

            function handleColumnToggle(key, isChecked, control) {
                if (isChecked) {
                    selectedColumns.add(key);
                } else {
                    if (selectedColumns.size === 1 && selectedColumns.has(key)) {
                        if (control) {
                            control.checked = true;
                        }
                        return;
                    }
                    selectedColumns.delete(key);
                }

                renderTable(processedRows);
                updateExportButtons();
            }

            function formatCell(value) {
                if (value === null || value === undefined || value === '') {
                    return '<span class="muted-text">—</span>';
                }
                return escapeHtml(String(value));
            }

            function renderTable(rows) {
                const visibleColumns = getVisibleColumns();

                if (table) {
                    if (selectedColumns.has('registro')) {
                        table.classList.add('prosa-table-with-index');
                    } else {
                        table.classList.remove('prosa-table-with-index');
                    }
                }

                if (visibleColumns.length === 0) {
                    tableHeadRow.innerHTML = '<th>Sin columnas seleccionadas</th>';
                    tableBody.innerHTML = '<tr><td>Selecciona al menos una columna para visualizar los datos.</td></tr>';
                    return;
                }

                tableHeadRow.innerHTML = visibleColumns.map(function (column) {
                    return '<th>' + escapeHtml(column.label) + '</th>';
                }).join('');

                if (!Array.isArray(rows) || rows.length === 0) {
                    tableBody.innerHTML = '<tr><td colspan="' + visibleColumns.length + '">Aún no hay datos procesados.</td></tr>';
                    return;
                }

                const html = rows.map(function (row) {
                    const cells = visibleColumns.map(function (column) {
                        return '<td>' + formatCell(row[column.key]) + '</td>';
                    }).join('');
                    return '<tr>' + cells + '</tr>';
                }).join('');

                tableBody.innerHTML = html;
            }

            function renderSummary(totalSegments, successes, errorCountValue) {
                if (totalSegments === 0) {
                    summary.textContent = 'No se detectaron objetos JSON en la entrada.';
                    return;
                }

                const parts = [successes + ' de ' + totalSegments + ' registros procesados correctamente.'];
                if (errorCountValue > 0) {
                    parts.push(errorCountValue + ' con errores.');
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

            function updateExportButtons() {
                const hasData = Array.isArray(processedRows) && processedRows.length > 0;
                const hasColumns = selectedColumns.size > 0;
                const disabled = !(hasData && hasColumns);

                exportJsonButton.disabled = disabled;
                exportCsvButton.disabled = disabled;
            }

            function escapeCsvValue(value) {
                if (value === null || value === undefined) {
                    return '';
                }

                const stringValue = String(value);

                if (stringValue === '') {
                    return '';
                }

                if (/[",\n\r]/.test(stringValue)) {
                    return '"' + stringValue.replace(/"/g, '""') + '"';
                }

                return stringValue;
            }

            function buildCsv(rows, columns) {
                const header = columns.map(function (column) {
                    return escapeCsvValue(column.label);
                }).join(',');

                const lines = rows.map(function (row) {
                    return columns.map(function (column) {
                        const value = row[column.key] ?? '';
                        return escapeCsvValue(value);
                    }).join(',');
                });

                return [header].concat(lines).join('\n');
            }

            function downloadBlob(blob, filename) {
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = filename;
                document.body.appendChild(link);
                link.click();
                requestAnimationFrame(function () {
                    document.body.removeChild(link);
                    URL.revokeObjectURL(url);
                });
            }

            function exportAsJson() {
                if (!Array.isArray(processedRows) || processedRows.length === 0) {
                    return;
                }

                const visibleColumns = getVisibleColumns();
                const data = processedRows.map(function (row) {
                    const item = {};
                    visibleColumns.forEach(function (column) {
                        item[column.key] = row[column.key] ?? null;
                    });
                    return item;
                });

                const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
                downloadBlob(blob, 'prosa-log-parser.json');
            }

            function exportAsCsv() {
                if (!Array.isArray(processedRows) || processedRows.length === 0) {
                    return;
                }

                const visibleColumns = getVisibleColumns();
                const csvContent = buildCsv(processedRows, visibleColumns);
                const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                downloadBlob(blob, 'prosa-log-parser.csv');
            }

            function handleProcess() {
                const rawInput = input.value;
                const result = parseLogs(rawInput);

                processedRows = result.results;

                renderSummary(result.segments, result.results.length, result.errors.length);
                renderTable(processedRows);
                renderErrors(result.errors);
                updateExportButtons();
            }

            function handleClear() {
                input.value = '';
                processedRows = [];
                summary.textContent = defaultSummary;
                renderTable(processedRows);
                renderErrors([]);
                updateExportButtons();
                updateButtonState();
            }

            function updateButtonState() {
                processButton.disabled = input.value.trim().length === 0;
            }

            renderColumnOptions();
            renderTable(processedRows);
            renderErrors([]);
            updateExportButtons();
            updateButtonState();

            processButton.addEventListener('click', handleProcess);
            clearButton.addEventListener('click', handleClear);
            input.addEventListener('input', updateButtonState);
            exportJsonButton.addEventListener('click', exportAsJson);
            exportCsvButton.addEventListener('click', exportAsCsv);
        })();
    </script>
    <?php
});
