<?php
declare(strict_types=1);

require __DIR__ . '/includes/template.php';

renderPage('Parser de Tokens', static function (): void {
    ?>
    <section class="tool-card token-tool">
        <header>
            <h2>Parser de Tokens FS y FT</h2>
            <p>Separador visual de los campos definidos para los tokens FS y FT seg&uacute;n las especificaciones de soporte.</p>
        </header>
        <div class="token-panels">
            <article class="token-panel" data-token-panel="fs">
                <h3>Token FS</h3>
                <p class="helper-text">Layout de 60 caracteres con datos de ciudad, estado, pa&iacute;s, c&oacute;digo postal y coordenadas.</p>
                <form class="token-form" data-token="fs">
                    <label>
                        <span>Token FS</span>
                        <textarea spellcheck="false" placeholder="! FS00050 CITY...........STECOUNTRYPOSTALCODEGEO-COORDINATES....."></textarea>
                    </label>
                    <div class="form-actions">
                        <button type="button" disabled>Analizar Token FS</button>
                    </div>
                </form>
                <div class="token-feedback" role="status" aria-live="polite"></div>
                <div class="token-results">
                    <table class="token-table">
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>Campo</th>
                            <th>Inicio</th>
                            <th>Long</th>
                            <th>Fin</th>
                            <th>Valor</th>
                            <th>Notas</th>
                        </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </article>

            <article class="token-panel" data-token-panel="ft">
                <h3>Token FT</h3>
                <p class="helper-text">Layout extendido de 130 caracteres con datos de contacto y tipo de comercio.</p>
                <form class="token-form" data-token="ft">
                    <label>
                        <span>Token FT</span>
                        <textarea spellcheck="false" placeholder="! FT00120PHONE...................ADDRESS.........................TNAME.............................................."></textarea>
                    </label>
                    <div class="form-actions">
                        <button type="button" disabled>Analizar Token FT</button>
                    </div>
                </form>
                <div class="token-feedback" role="status" aria-live="polite"></div>
                <div class="token-results">
                    <table class="token-table">
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>Campo</th>
                            <th>Inicio</th>
                            <th>Long</th>
                            <th>Fin</th>
                            <th>Valor</th>
                            <th>Notas</th>
                        </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </article>
        </div>
    </section>

    <script>
        (function () {
            const tokenSchemas = {
                fs: {
                    label: 'Token FS',
                    fields: [
                        { id: 'H1', name: 'EYE-CATCHER', start: 1, length: 1, description: 'Inicio del token.', expected: '!', expectedLabel: '!' },
                        { id: 'H2', name: 'USER-FLD1', start: 2, length: 1, description: 'Separador fijo.', expected: ' ', expectedLabel: 'espacio' },
                        { id: 'H3', name: 'Identificador del Token', start: 3, length: 2, description: 'Identificador FS.', expected: 'FS', expectedLabel: 'FS' },
                        { id: 'H4', name: 'Longitud de datos', start: 5, length: 5, description: 'Longitud declarada del bloque de datos.', expected: '00050', expectedLabel: '00050' },
                        { id: 'H5', name: 'USER-FLD2', start: 10, length: 1, description: 'Separador fijo.', expected: ' ', expectedLabel: 'espacio' },
                        { id: '1', name: 'CITY', start: 11, length: 13, description: 'Ciudad donde opera el comercio.' },
                        { id: '2', name: 'ST', start: 24, length: 3, description: 'Estado o provincia del comercio.' },
                        { id: '3', name: 'CNTRY-CDE', start: 27, length: 3, description: 'Codigo ISO alpha-3 del pais del comercio.' },
                        { id: '4', name: 'POSTAL-CDE', start: 30, length: 10, description: 'Codigo postal del comercio.' },
                        { id: '5', name: 'GEO-COORD', start: 40, length: 20, description: 'Coordenadas geograficas (latitud y longitud).' },
                        { id: '6', name: 'USE-FLD-ACI', start: 60, length: 1, description: 'Reservado para uso futuro.' }
                    ]
                },
                ft: {
                    label: 'Token FT',
                    fields: [
                        { id: 'H1', name: 'EYE-CATCHER', start: 1, length: 1, description: 'Inicio del token.', expected: '!', expectedLabel: '!' },
                        { id: 'H2', name: 'USER-FLD1', start: 2, length: 1, description: 'Separador fijo.', expected: ' ', expectedLabel: 'espacio' },
                        { id: 'H3', name: 'Identificador del Token', start: 3, length: 2, description: 'Identificador FT.', expected: 'FT', expectedLabel: 'FT' },
                        { id: 'H4', name: 'Longitud de datos', start: 5, length: 5, description: 'Longitud declarada del bloque de datos.', expected: '00120', expectedLabel: '00120' },
                        { id: 'H5', name: 'USER-FLD2', start: 10, length: 1, description: 'Separador fijo.', expected: ' ', expectedLabel: 'espacio' },
                        { id: '1', name: 'CUST-SRVC-PHONE', start: 11, length: 20, description: 'Telefono de atencion del comercio.' },
                        { id: '2', name: 'STR-ADDR', start: 31, length: 25, description: 'Direccion del comercio que posee la terminal.' },
                        { id: '3', name: 'RETL-TYP', start: 56, length: 1, description: 'Tipo de comercio (M, P o S).' },
                        { id: '4', name: 'RETL-NAM', start: 57, length: 40, description: 'Nombre del comercio propietario de la terminal.' },
                        { id: '5', name: 'USER-FLD-ACI', start: 97, length: 34, description: 'Reservado para uso futuro.' }
                    ]
                }
            };

            Object.values(tokenSchemas).forEach(function (schema) {
                schema.expectedLength = Math.max.apply(null, schema.fields.map(function (field) {
                    return field.start + field.length - 1;
                }));
            });

            function escapeHtml(value) {
                return value.replace(/[&<>"']/g, function (char) {
                    switch (char) {
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
                            return char;
                    }
                });
            }

            document.querySelectorAll('.token-form').forEach(function (form) {
                const panel = form.closest('.token-panel');
                if (!panel) {
                    return;
                }

                const tokenType = form.getAttribute('data-token');
                const schema = tokenSchemas[tokenType];
                if (!schema) {
                    return;
                }

                const textarea = form.querySelector('textarea');
                const button = form.querySelector('button[type="button"]');
                const feedback = panel.querySelector('.token-feedback');
                const tableBody = panel.querySelector('.token-table tbody');

                function updateButtonState() {
                    const hasContent = textarea.value.replace(/\s+/g, '').length > 0;
                    button.disabled = !hasContent;
                }

                function buildNotes(field, value) {
                    const notes = [field.description];
                    if (typeof field.expected === 'string') {
                        if (value === field.expected) {
                            notes.push('Coincide con el valor esperado ' + field.expectedLabel + '.');
                        } else {
                            notes.push('Se esperaba ' + field.expectedLabel + '.');
                        }
                    }
                    if (value.length < field.length) {
                        notes.push('Valor incompleto. Se esperaban ' + field.length + ' caracteres.');
                    }
                    return notes.join(' ');
                }

                function renderTableRows(rows) {
                    return rows.map(function (row) {
                        return '<tr>' +
                            '<td>' + escapeHtml(row.id) + '</td>' +
                            '<td>' + escapeHtml(row.name) + '</td>' +
                            '<td>' + row.start + '</td>' +
                            '<td>' + row.length + '</td>' +
                            '<td>' + row.finish + '</td>' +
                            '<td><span class="token-value">' + escapeHtml(row.value) + '</span></td>' +
                            '<td>' + escapeHtml(row.notes) + '</td>' +
                            '</tr>';
                    }).join('');
                }

                textarea.addEventListener('input', updateButtonState);
                updateButtonState();

                button.addEventListener('click', function () {
                    const rawInput = textarea.value.replace(/\r?\n/g, '');
                    const token = rawInput;
                    const tokenLength = token.length;
                    const rows = schema.fields.map(function (field) {
                        const startIndex = field.start - 1;
                        const endIndex = startIndex + field.length;
                        const value = token.slice(startIndex, endIndex);
                        return {
                            id: field.id,
                            name: field.name,
                            start: field.start,
                            length: field.length,
                            finish: field.start + field.length - 1,
                            value: value,
                            notes: buildNotes(field, value)
                        };
                    });

                    const warnings = [];
                    if (tokenLength < schema.expectedLength) {
                        warnings.push('Token con longitud menor a la esperada (' + tokenLength + '/' + schema.expectedLength + ').');
                    }
                    if (tokenLength > schema.expectedLength) {
                        warnings.push('Token con ' + (tokenLength - schema.expectedLength) + ' caracteres adicionales al final.');
                    }

                    tableBody.innerHTML = renderTableRows(rows);
                    if (warnings.length > 0) {
                        feedback.textContent = warnings.join(' ');
                        feedback.classList.remove('ok');
                    } else if (tokenLength === 0) {
                        feedback.textContent = 'Ingresa un token para iniciar el analisis.';
                        feedback.classList.remove('ok');
                    } else {
                        feedback.textContent = 'Analisis completado. Token de ' + tokenLength + ' caracteres.';
                        feedback.classList.add('ok');
                    }
                });
            });
        })();
    </script>
    <?php
});
