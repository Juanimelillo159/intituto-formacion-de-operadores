ALTER TABLE cursos
  ADD COLUMN duracion_certificacion VARCHAR(255) NULL AFTER duracion,
  ADD COLUMN descripcion_certificacion TEXT NULL AFTER descripcion_curso,
  ADD COLUMN objetivos_certificacion TEXT NULL AFTER objetivos,
  ADD COLUMN programa_certificacion TEXT NULL AFTER programa,
  ADD COLUMN publico_certificacion TEXT NULL AFTER publico,
  ADD COLUMN cronograma_certificacion TEXT NULL AFTER cronograma,
  ADD COLUMN requisitos_certificacion TEXT NULL AFTER requisitos,
  ADD COLUMN observaciones_certificacion TEXT NULL AFTER observaciones,
  ADD COLUMN documentacion_certificacion TEXT NULL AFTER documentacion;
