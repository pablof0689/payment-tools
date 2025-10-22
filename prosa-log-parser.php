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

            const columnGroups = [
                {
                    name: 'Metadatos del log',
                    description: 'Identificadores principales del evento recibido.',
                    columns: [
                        {
                            key: 'correlation_id',
                            label: 'Correlation ID',
                            hint: 'mdc.correlation_id',
                            getValue: function (context) {
                                return context.correlationId;
                            }
                        },
                        {
                            key: 'transactionUUID',
                            label: 'Transaction UUID',
                            hint: 'internal.transactionUUID',
                            getValue: function (context) {
                                return context.transactionUuid;
                            }
                        },
                        {
                            key: 'timestamp',
                            label: 'Timestamp',
                            hint: 'timestamp',
                            getValue: function (context) {
                                return context.timestamp;
                            }
                        }
                    ]
                },
                {
                    name: 'Entidad',
                    description: 'Datos de la entidad vinculada al contrato.',
                    columns: [
                        {
                            key: 'internal.entity.name',
                            label: 'Nombre de la entidad',
                            hint: 'internal.entity.name',
                            getValue: function (context) {
                                return asCleanString(context.entity.name);
                            }
                        },
                        {
                            key: 'internal.entity.mcc',
                            label: 'MCC',
                            hint: 'internal.entity.mcc',
                            getValue: function (context) {
                                return asCleanString(context.entity.mcc);
                            }
                        },
                        {
                            key: 'internal.entity.entityUid',
                            label: 'Entity UID',
                            hint: 'internal.entity.entityUid',
                            getValue: function (context) {
                                return asCleanString(context.entity.entityUid);
                            }
                        }
                    ]
                },
                {
                    name: 'Contrato de pago',
                    description: 'Información del contrato y procesador configurado.',
                    columns: [
                        {
                            key: 'internal.paymentContract.name',
                            label: 'Nombre del contrato',
                            hint: 'internal.paymentContract.name',
                            getValue: function (context) {
                                return asCleanString(context.paymentContract.name);
                            }
                        },
                        {
                            key: 'internal.paymentContract.merchantId',
                            label: 'Merchant ID',
                            hint: 'internal.paymentContract.merchantId',
                            getValue: function (context) {
                                return asCleanString(context.paymentContract.merchantId);
                            }
                        },
                        {
                            key: 'internal.paymentContract.contractUid',
                            label: 'Contract UID',
                            hint: 'internal.paymentContract.contractUid',
                            getValue: function (context) {
                                return asCleanString(context.paymentContract.contractUid);
                            }
                        },
                        {
                            key: 'internal.paymentContract.processor.type',
                            label: 'Procesador - Tipo',
                            hint: 'internal.paymentContract.processor.type',
                            getValue: function (context) {
                                return asCleanString(context.paymentProcessor.type);
                            }
                        },
                        {
                            key: 'internal.paymentContract.processor.acquirer',
                            label: 'Procesador - Adquirente',
                            hint: 'internal.paymentContract.processor.acquirer',
                            getValue: function (context) {
                                return asCleanString(context.paymentProcessor.acquirer);
                            }
                        }
                    ]
                },
                {
                    name: 'Terminal (POI)',
                    description: 'Identificadores del punto de venta y del dispositivo.',
                    columns: [
                        {
                            key: 'internal.poi.poiUid',
                            label: 'POI UID',
                            hint: 'internal.poi.poiUid',
                            getValue: function (context) {
                                return asCleanString(context.poi.poiUid);
                            }
                        },
                        {
                            key: 'internal.poi.device.serialNumber',
                            label: 'Serial del dispositivo',
                            hint: 'internal.poi.device.serialNumber',
                            getValue: function (context) {
                                return asCleanString(context.poiDevice.serialNumber);
                            }
                        },
                        {
                            key: 'internal.poi.device.deviceUid',
                            label: 'Device UID',
                            hint: 'internal.poi.device.deviceUid',
                            getValue: function (context) {
                                return asCleanString(context.poiDevice.deviceUid);
                            }
                        },
                        {
                            key: 'transaction.poi.software.version',
                            label: 'Versión del software',
                            hint: 'transaction.poi.software.version',
                            getValue: function (context) {
                                return context.softwareVersion;
                            }
                        },
                        {
                            key: 'transaction.poi.device.communicationMethod',
                            label: 'Método de comunicación',
                            hint: 'transaction.poi.device.communicationMethod',
                            getValue: function (context) {
                                return context.communicationMethod;
                            }
                        }
                    ]
                },
                {
                    name: 'Instrumento de pago',
                    description: 'Detalles del medio de pago utilizado.',
                    columns: [
                        {
                            key: 'paymentData.instrument.maskedCardNumber',
                            label: 'Masked PAN',
                            hint: 'paymentData.instrument.maskedCardNumber',
                            getValue: function (context) {
                                return asCleanString(context.instrument && context.instrument.maskedCardNumber);
                            }
                        },
                        {
                            key: 'paymentData.instrument.cardData.cardholderName',
                            label: 'Nombre del tarjetahabiente',
                            hint: 'paymentData.instrument.cardData.cardholderName',
                            getValue: function (context) {
                                return asCleanString(context.cardData.cardholderName);
                            }
                        },
                        {
                            key: 'paymentData.instrument.cardBrand',
                            label: 'Marca de la tarjeta',
                            hint: 'paymentData.instrument.cardBrand',
                            getValue: function (context) {
                                return asCleanString(context.instrument && context.instrument.cardBrand);
                            }
                        },
                        {
                            key: 'paymentData.instrument.cardProduct',
                            label: 'Producto de la tarjeta',
                            hint: 'paymentData.instrument.cardProduct',
                            getValue: function (context) {
                                return asCleanString(context.instrument && context.instrument.cardProduct);
                            }
                        }
                    ]
                },
                {
                    name: 'Contexto y transacción',
                    description: 'Información de la operación registrada.',
                    columns: [
                        {
                            key: 'paymentContext.entryMode',
                            label: 'Modo de entrada',
                            hint: 'paymentContext.entryMode',
                            getValue: function (context) {
                                return context.entryMode;
                            }
                        },
                        {
                            key: 'transaction.referenceId',
                            label: 'Referencia',
                            hint: 'transaction.referenceId',
                            getValue: function (context) {
                                return asCleanString(context.transaction.referenceId);
                            }
                        },
                        {
                            key: 'transaction.transactionType',
                            label: 'Tipo de transacción',
                            hint: 'transaction.transactionType',
                            getValue: function (context) {
                                return asCleanString(context.transaction.transactionType);
                            }
                        },
                        {
                            key: 'transaction.totalAmount.currencyCode',
                            label: 'Moneda',
                            hint: 'transaction.totalAmount.currencyCode',
                            getValue: function (context) {
                                return asCleanString(context.totalAmount.currencyCode);
                            }
                        },
                        {
                            key: 'transaction.totalAmount.value',
                            label: 'Importe',
                            hint: 'transaction.totalAmount.value',
                            getValue: function (context) {
                                return asCleanString(context.totalAmount.value);
                            }
                        },
                        {
                            key: 'transaction.createdDateTime',
                            label: 'Fecha de creación',
                            hint: 'transaction.createdDateTime',
                            getValue: function (context) {
                                return asCleanString(context.transaction.createdDateTime);
                            }
                        }
                    ]
                }
            ];

            const columnDefinitions = [];
            columnGroups.forEach(function (group) {
                group.columns.forEach(function (column) {
                    if (!column.exportLabel) {
                        column.exportLabel = column.key;
                    }
                    columnDefinitions.push(column);
                });
            });

            const defaultColumnKeys = ['correlation_id', 'transactionUUID', 'timestamp'];
            const selectedColumns = new Set(defaultColumnKeys.filter(function (key) {
                return columnDefinitions.some(function (column) {
                    return column.key === key;
                });
            }));

            if (selectedColumns.size === 0 && columnDefinitions.length > 0) {
                selectedColumns.add(columnDefinitions[0].key);
            }
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

                    sanitizedLine = sanitizedLine.replace(/""/g, '"');
                    sanitizedLine = sanitizedLine.replace(/\\"/g, '"');
                    sanitizedLine = sanitizedLine.replace(/^"+|"+$/g, '');
                    sanitizedLine = sanitizedLine.replace(/^'+|'+$/g, '');
                    sanitizedLine = sanitizedLine.replace(/\\n/g, '');
                    sanitizedLine = sanitizedLine.replace(/,+\s*$/g, '');

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

            function normaliseEntryMode(value) {
                if (Array.isArray(value)) {
                    const parts = value.map(function (item) {
                        const cleaned = asCleanString(item);
                        return cleaned || null;
                    }).filter(function (item) {
                        return item !== null;
                    });

                    return parts.length > 0 ? parts.join(', ') : null;
                }

                return asCleanString(value);
            }

            function extractSoftwareVersion(value) {
                if (Array.isArray(value)) {
                    for (let index = 0; index < value.length; index += 1) {
                        const candidate = value[index];

                        if (typeof candidate === 'string') {
                            const cleanedString = asCleanString(candidate);
                            if (cleanedString) {
                                return cleanedString;
                            }
                        } else if (candidate && typeof candidate === 'object') {
                            const cleanedVersion = asCleanString(candidate.version);
                            if (cleanedVersion) {
                                return cleanedVersion;
                            }
                        }
                    }

                    return null;
                }

                if (value && typeof value === 'object') {
                    return asCleanString(value.version);
                }

                return asCleanString(value);
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
                        const internal = detail.internal && typeof detail.internal === 'object' ? detail.internal : {};
                        const entity = internal.entity && typeof internal.entity === 'object' ? internal.entity : {};
                        const poi = internal.poi && typeof internal.poi === 'object' ? internal.poi : {};
                        const poiDevice = poi.device && typeof poi.device === 'object' ? poi.device : {};
                        const paymentContract = internal.paymentContract && typeof internal.paymentContract === 'object' ? internal.paymentContract : {};
                        const paymentProcessor = paymentContract.processor && typeof paymentContract.processor === 'object' ? paymentContract.processor : {};
                        const paymentContext = detail.paymentContext && typeof detail.paymentContext === 'object' ? detail.paymentContext : {};
                        const paymentData = detail.paymentData && typeof detail.paymentData === 'object' ? detail.paymentData : {};
                        const transaction = detail.transaction && typeof detail.transaction === 'object' ? detail.transaction : {};
                        const totalAmount = transaction.totalAmount && typeof transaction.totalAmount === 'object' ? transaction.totalAmount : {};
                        const transactionPoi = transaction.poi && typeof transaction.poi === 'object' ? transaction.poi : {};
                        const transactionPoiDevice = transactionPoi.device && typeof transactionPoi.device === 'object' ? transactionPoi.device : {};
                        const transactionPoiSoftware = transactionPoi.software;

                        let instrument = null;
                        if (Array.isArray(paymentData.instrument)) {
                            for (let instrumentIndex = 0; instrumentIndex < paymentData.instrument.length; instrumentIndex += 1) {
                                const candidate = paymentData.instrument[instrumentIndex];
                                if (candidate && typeof candidate === 'object') {
                                    instrument = candidate;
                                    break;
                                }
                            }
                        } else if (paymentData.instrument && typeof paymentData.instrument === 'object') {
                            instrument = paymentData.instrument;
                        }

                        const cardData = instrument && typeof instrument.cardData === 'object' ? instrument.cardData : {};

                        const correlationId = asCleanString(getNested(base, ['mdc', 'correlation_id'])) || asCleanString(getNested(base, ['mdc', 'correlationId']));
                        const timestamp = asCleanString(base.timestamp);
                        const transactionUuid = asCleanString(internal.transactionUUID);
                        const entryMode = normaliseEntryMode(paymentContext.entryMode);
                        const softwareVersion = extractSoftwareVersion(transactionPoiSoftware);
                        const communicationMethod = asCleanString(transactionPoiDevice.communicationMethod);

                        const context = {
                            base,
                            detail,
                            internal,
                            entity,
                            poi,
                            poiDevice,
                            paymentContract,
                            paymentProcessor,
                            paymentContext,
                            paymentData,
                            instrument,
                            cardData,
                            transaction,
                            totalAmount,
                            transactionPoi,
                            transactionPoiDevice,
                            correlationId,
                            timestamp,
                            transactionUuid,
                            entryMode,
                            softwareVersion,
                            communicationMethod
                        };

                        const entry = {};
                        columnDefinitions.forEach(function (column) {
                            entry[column.key] = column.getValue(context);
                        });

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

                columnGroups.forEach(function (group) {
                    const groupWrapper = document.createElement('section');
                    groupWrapper.className = 'prosa-column-group';

                    const header = document.createElement('div');
                    header.className = 'prosa-column-group-header';

                    const title = document.createElement('h4');
                    title.textContent = group.name;
                    header.appendChild(title);

                    if (group.description) {
                        const description = document.createElement('p');
                        description.className = 'prosa-column-group-description';
                        description.textContent = group.description;
                        header.appendChild(description);
                    }

                    groupWrapper.appendChild(header);

                    const optionsContainer = document.createElement('div');
                    optionsContainer.className = 'prosa-column-group-options';

                    group.columns.forEach(function (column) {
                        const optionLabel = document.createElement('label');
                        optionLabel.className = 'prosa-column-option';

                        const checkbox = document.createElement('input');
                        checkbox.type = 'checkbox';
                        checkbox.value = column.key;
                        checkbox.checked = selectedColumns.has(column.key);
                        checkbox.addEventListener('change', function (event) {
                            handleColumnToggle(column.key, checkbox.checked, event.target);
                        });

                        const textWrapper = document.createElement('div');
                        textWrapper.className = 'prosa-column-text';

                        const titleSpan = document.createElement('span');
                        titleSpan.className = 'prosa-column-title';
                        titleSpan.textContent = column.label;
                        titleSpan.appendChild(checkbox);
                        textWrapper.appendChild(titleSpan);

                        if (column.hint) {
                            const hintSpan = document.createElement('span');
                            hintSpan.className = 'prosa-column-hint';
                            hintSpan.textContent = column.hint;
                            textWrapper.appendChild(hintSpan);
                        }

                        
                        optionLabel.appendChild(textWrapper);
                        optionsContainer.appendChild(optionLabel);
                    });

                    groupWrapper.appendChild(optionsContainer);
                    fragment.appendChild(groupWrapper);
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
                    const headerLabel = column.exportLabel || column.label;
                    return escapeCsvValue(headerLabel);
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
