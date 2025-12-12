ALTER TABLE cursos
  CHANGE requisitos prerrequisitos TEXT NULL AFTER cronograma,
  CHANGE requisitos_certificacion prerrequisitos_certificacion TEXT NULL AFTER cronograma_certificacion;

ALTER TABLE certificaciones
  CHANGE requisitos prerrequisitos TEXT NULL AFTER alcance;
