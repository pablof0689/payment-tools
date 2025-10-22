<?php
declare(strict_types=1);

require __DIR__ . '/includes/template.php';

renderPage('Parser de Tokens', static function (): void {
    ?>
    <section class="tool-card">
        <header>
            <h2>Parser de Tokens</h2>
            <p>Analiza tokens firmados (JWT u otros formatos propietarios) y separa sus secciones claves.</p>
        </header>
        <form class="token-form">
            <label>
                <span>Token</span>
                <textarea placeholder="Pega aquí el token para revisar"></textarea>
            </label>
            <div class="token-output">
                <div class="token-section">
                    <h3>Header</h3>
                    <p class="helper-text">Algoritmo, tipo y metadatos.</p>
                </div>
                <div class="token-section">
                    <h3>Payload</h3>
                    <p class="helper-text">Claims y datos de la transacción.</p>
                </div>
                <div class="token-section">
                    <h3>Firma</h3>
                    <p class="helper-text">Sello criptográfico para validar integridad.</p>
                </div>
            </div>
            <button type="button" disabled>Descomponer Token (próximamente)</button>
        </form>
    </section>
    <?php
});
