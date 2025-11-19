-- Ajustes para precios por modalidad en capacitaciones

-- 1) Registrar modalidad en el historial de precios de cursos
ALTER TABLE curso_precio_hist
  ADD COLUMN id_modalidad INT(11) DEFAULT NULL AFTER id_curso,
  ADD INDEX idx_cph_modalidad (id_modalidad),
  ADD CONSTRAINT fk_cph_modalidad FOREIGN KEY (id_modalidad) REFERENCES modalidades (id_modalidad);

-- 2) Guardar modalidad elegida en los checkouts de capacitaciones
ALTER TABLE checkout_capacitaciones
  ADD COLUMN id_modalidad INT(11) DEFAULT NULL AFTER id_curso,
  ADD INDEX idx_checkout_modalidad (id_modalidad),
  ADD CONSTRAINT fk_checkout_modalidad FOREIGN KEY (id_modalidad) REFERENCES modalidades (id_modalidad);
