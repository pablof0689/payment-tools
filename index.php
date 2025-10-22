<?php
declare(strict_types=1);

require __DIR__ . '/includes/template.php';

renderPage('Inicio', static function (): void {
    ?>
    <section class="hero-card">
        <h2>Bienvenido a Payment Tools</h2>
        <p>
            Este panel reúne herramientas clave para agilizar el análisis y el soporte del gateway de pagos.
            Utiliza el menú para acceder rápidamente a cada asistente y agrega nuevas utilidades simplemente
            incorporando un archivo PHP y registrando el enlace en <code>includes/menu.php</code>.
        </p>
    </section>
    <section>
        <div class="tools-grid">
            <article class="tool-card">
                <header>
                    <h2>Corrector de JSON</h2>
                    <p>Revisa, valida y formatea rápidamente estructuras JSON para integraciones ISO 8583.</p>
                </header>
                <p class="helper-text">Accede desde el menú para comenzar a depurar tus mensajes.</p>
                <a class="cta-button" href="json-corrector.php">Abrir herramienta</a>
            </article>
            <article class="tool-card">
                <header>
                    <h2>TLV Parser</h2>
                    <p>Convierte cadenas TLV en un desglose visual por etiquetas, longitudes y valores.</p>
                </header>
                <p class="helper-text">Pensado para mensajes EMV y campos extendidos de ISO 8583.</p>
                <a class="cta-button" href="tlv-parser.php">Abrir herramienta</a>
            </article>
            <article class="tool-card">
                <header>
                    <h2>Parser de Tokens</h2>
                    <p>Descompone tokens de seguridad para inspeccionar encabezados, payload y firmas.</p>
                </header>
                <p class="helper-text">Ideal para validar integraciones JWT o equivalentes propietarios.</p>
                <a class="cta-button" href="token-parser.php">Abrir herramienta</a>
            </article>
            <article class="tool-card">
                <header>
                    <h2>Prosa Log Parser</h2>
                    <p>Resume hasta 500 registros de Kibana para identificar referencias, montos y PAN enmascarados.</p>
                </header>
                <p class="helper-text">Pega los logs crudos y obtén un JSON consolidado listo para compartir.</p>
                <a class="cta-button" href="prosa-log-parser.php">Abrir herramienta</a>
            </article>
        </div>
    </section>
    <?php
});
