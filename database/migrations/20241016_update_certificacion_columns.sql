ALTER TABLE cursos
  DROP COLUMN IF EXISTS duracion_certificacion,
  DROP COLUMN IF EXISTS objetivos_certificacion,
  DROP COLUMN IF EXISTS programa_certificacion,
  DROP COLUMN IF EXISTS publico_certificacion,
  DROP COLUMN IF EXISTS cronograma_certificacion,
  DROP COLUMN IF EXISTS observaciones_certificacion,
  ADD COLUMN IF NOT EXISTS requisitos_evaluacion_certificacion TEXT NULL AFTER descripcion_certificacion,
  ADD COLUMN IF NOT EXISTS proceso_certificacion TEXT NULL AFTER requisitos_evaluacion_certificacion,
  ADD COLUMN IF NOT EXISTS alcance_certificacion TEXT NULL AFTER proceso_certificacion,
  ADD COLUMN IF NOT EXISTS vigencia_certificacion TEXT NULL AFTER prerrequisitos_certificacion,
  ADD COLUMN IF NOT EXISTS documentacion_certificacion TEXT NULL AFTER vigencia_certificacion,
  ADD COLUMN IF NOT EXISTS plazo_certificacion VARCHAR(255) NULL AFTER documentacion_certificacion;
