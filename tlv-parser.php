<?php
declare(strict_types=1);

require __DIR__ . '/includes/template.php';

renderPage('TLV Parser', static function (): void {
    ?>
    <section class="tool-card">
        <header>
            <h2>TLV Parser</h2>
            <p>Prepara un desglose rápido de etiquetas (Tag), longitudes (Length) y valores (Value) en cadenas TLV.</p>
        </header>
        <form>
            <label>
                <span>Cadena TLV</span>
                <textarea placeholder="Introduce la secuencia TLV para analizar"></textarea>
            </label>
            <div class="tlv-output">
                <div class="tlv-columns">
                    <div>
                        <h3>Tag</h3>
                        <p class="helper-text">Ej: 9F26</p>
                    </div>
                    <div>
                        <h3>Length</h3>
                        <p class="helper-text">Ej: 08</p>
                    </div>
                    <div>
                        <h3>Value</h3>
                        <p class="helper-text">Ej: F1A2B3C4D5E6F7A8</p>
                    </div>
                </div>
                <p class="helper-text">La salida aparecerá aquí una vez que activemos el parser.</p>
            </div>
            <button type="button" disabled>Procesar TLV (próximamente)</button>
        </form>
    </section>
    <?php
});
