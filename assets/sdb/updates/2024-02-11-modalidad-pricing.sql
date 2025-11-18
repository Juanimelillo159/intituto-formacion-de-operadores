-- Cambios necesarios para soportar precios por modalidad y registrar la modalidad elegida en cada checkout.
-- Ejecutá cada ALTER sólo si tu base todavía no tiene la columna/índice/foreign key correspondiente.

-- 1) Guardar la modalidad utilizada cuando se crea un precio nuevo.
ALTER TABLE curso_precio_hist
    ADD COLUMN id_modalidad INT(11) NULL AFTER tipo_curso,
    ADD KEY idx_cph_modalidad (id_modalidad),
    ADD CONSTRAINT fk_cph_modalidad FOREIGN KEY (id_modalidad) REFERENCES modalidades (id_modalidad);

-- 2) Guardar la modalidad elegida en cada checkout de capacitación.
ALTER TABLE checkout_capacitaciones
    ADD COLUMN id_modalidad INT(11) NULL AFTER id_curso,
    ADD CONSTRAINT fk_checkout_capacitaciones_modalidad FOREIGN KEY (id_modalidad) REFERENCES modalidades (id_modalidad);
