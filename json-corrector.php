<?php
declare(strict_types=1);

require __DIR__ . '/includes/template.php';

renderPage('Corrector de JSON', static function (): void {
    ?>
    <section class="tool-card">
        <header>
            <h2>Corrector de JSON</h2>
            <p>Formatea y valida JSON de mensajes ISO 8583 antes de enviarlos a producción.</p>
        </header>
        <form>
            <label>
                <span>Entrada JSON</span>
                <textarea placeholder='{
    "field-48": "Introduce tu mensaje aquí"
}'></textarea>
            </label>
            <label>
                <span>Resultado</span>
                <textarea placeholder="El resultado formateado aparecerá aquí" readonly></textarea>
            </label>
            <button type="button" disabled>Formatear JSON (próximamente)</button>
        </form>
        <p class="helper-text">Esta vista previa te permitirá validar estructuras complejas sin depender de herramientas externas.</p>
    </section>
    <?php
});
