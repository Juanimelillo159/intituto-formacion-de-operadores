<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';

if (!isset($_SESSION['id_usuario']) && !isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit;
}

$userId = (int)($_SESSION['id_usuario'] ?? $_SESSION['usuario'] ?? 0);

if ($userId <= 0 && isset($_SESSION['email'])) {
    try {
        $pdo = getPdo();
        $stmtUserLookup = $pdo->prepare('SELECT id_usuario FROM usuarios WHERE email = :email LIMIT 1');
        $stmtUserLookup->bindValue(':email', (string)$_SESSION['email'], PDO::PARAM_STR);
        $stmtUserLookup->execute();
        $fetchedUserId = (int)$stmtUserLookup->fetchColumn();
        if ($fetchedUserId > 0) {
            $userId = $fetchedUserId;
            $_SESSION['id_usuario'] = $userId;
            if (!isset($_SESSION['usuario']) || !is_numeric($_SESSION['usuario'])) {
                $_SESSION['usuario'] = $userId;
            }
        }
    } catch (Throwable $lookupException) {
        error_log('mis_cursos fallback lookup: ' . $lookupException->getMessage());
    }
}

if ($userId <= 0) {
    header('Location: login.php');
    exit;
}

$currentPermiso = (int)($_SESSION['permiso'] ?? 0);
$isHrManager = $currentPermiso === 3;
$userEmail = isset($_SESSION['email']) ? trim((string)$_SESSION['email']) : '';

$misCursosFeedback = $_SESSION['mis_cursos_feedback'] ?? null;
if ($misCursosFeedback !== null) {
    unset($_SESSION['mis_cursos_feedback']);
}

$allowedFeedbackTypes = ['success', 'info', 'warning', 'danger'];

$redirectWithFeedback = static function (array $payload) {
    $_SESSION['mis_cursos_feedback'] = $payload;
    header('Location: mis_cursos.php');
    exit;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isHrManager) {
        $redirectWithFeedback(['type' => 'danger', 'message' => 'No tenes permisos para asignar cursos a trabajadores.']);
    }

    $action = trim((string)($_POST['action'] ?? ''));
    if ($action === 'assign_worker') {
        $singleWorkerId = (int)($_POST['worker_id'] ?? 0);
        $_POST['worker_ids'] = $singleWorkerId > 0 ? [$singleWorkerId] : [];
        $action = 'assign_workers';
    }

    if ($action === 'assign_workers') {
        $itemTokenRaw = $_POST['item_id'] ?? '';
        $itemToken = trim((string)$itemTokenRaw);
        $workerIdsInput = $_POST['worker_ids'] ?? [];
        if (!is_array($workerIdsInput)) {
            $workerIdsInput = [$workerIdsInput];
        }

        $workerIds = [];
        foreach ($workerIdsInput as $workerValue) {
            $workerId = (int)$workerValue;
            if ($workerId > 0) {
                $workerIds[$workerId] = $workerId;
            }
        }
        $workerIds = array_values($workerIds);

        if ($itemToken === '' || empty($workerIds)) {
            $redirectWithFeedback(['type' => 'danger', 'message' => 'Selecciona al menos un trabajador y un curso validos.']);
        }

        $assignmentType = null;
        $courseId = 0;
        $legacyItemId = null;

        if (preg_match('/^(cap|cert)-(\d+)$/', $itemToken, $matches)) {
            $assignmentType = $matches[1];
            $courseId = (int)$matches[2];
        } elseif (ctype_digit($itemToken)) {
            $legacyItemId = (int)$itemToken;
        }

        if ($assignmentType === 'cap') {
            try {
                $pdo = getPdo();
                $pdo->beginTransaction();

                $seatLockSql = <<<'SQL'
SELECT
    cc.id_capacitacion,
    cc.email,
    cc.id_estado,
    u.id_usuario AS assigned_user_id
FROM checkout_capacitaciones cc
LEFT JOIN usuarios u ON u.email = cc.email
WHERE cc.creado_por = :usuario AND cc.id_curso = :curso
FOR UPDATE
SQL;
                $stmtSeats = $pdo->prepare($seatLockSql);
                $stmtSeats->bindValue(':usuario', $userId, PDO::PARAM_INT);
                $stmtSeats->bindValue(':curso', $courseId, PDO::PARAM_INT);
                $stmtSeats->execute();

                $seatRows = $stmtSeats->fetchAll(PDO::FETCH_ASSOC);
                if (empty($seatRows)) {
                    $pdo->rollBack();
                    $redirectWithFeedback(['type' => 'danger', 'message' => 'No se encontro el curso seleccionado.']);
                }

                $availableSeats = [];
                $assignedUserIds = [];
                $assignedEmails = [];

                foreach ($seatRows as $seatRow) {
                    $seatId = (int)($seatRow['id_capacitacion'] ?? 0);
                    if ($seatId <= 0) {
                        continue;
                    }
                    $email = trim((string)($seatRow['email'] ?? ''));
                    if ($email === '') {
                        $availableSeats[] = $seatId;
                    } else {
                        $assignedEmails[strtolower($email)] = true;
                        if ($seatRow['assigned_user_id'] !== null) {
                            $assignedUserIds[(int)$seatRow['assigned_user_id']] = true;
                        }
                    }
                }

                if (empty($availableSeats)) {
                    $pdo->rollBack();
                    $redirectWithFeedback(['type' => 'danger', 'message' => 'No quedan cupos disponibles para este curso.']);
                }

                if (count($workerIds) > count($availableSeats)) {
                    $pdo->rollBack();
                    $redirectWithFeedback(['type' => 'warning', 'message' => 'Seleccionaste mas trabajadores que cupos disponibles.']);
                }

                $placeholders = implode(',', array_fill(0, count($workerIds), '?'));
                $stmtMembership = $pdo->prepare('SELECT id_trabajador FROM empresa_trabajadores WHERE id_empresa = ? AND id_trabajador IN (' . $placeholders . ')');
                $membershipParams = array_merge([$userId], $workerIds);
                $stmtMembership->execute($membershipParams);
                $validMembers = array_map('intval', $stmtMembership->fetchAll(PDO::FETCH_COLUMN));
                $missingWorkers = array_diff($workerIds, $validMembers);
                if (!empty($missingWorkers)) {
                    $pdo->rollBack();
                    $redirectWithFeedback(['type' => 'danger', 'message' => 'Algunos trabajadores seleccionados no pertenecen a tu empresa.']);
                }

                $stmtWorkersData = $pdo->prepare('SELECT id_usuario, nombre, apellido, email, telefono FROM usuarios WHERE id_usuario IN (' . $placeholders . ')');
                $stmtWorkersData->execute($workerIds);
                $workersData = [];
                while ($workerRow = $stmtWorkersData->fetch(PDO::FETCH_ASSOC)) {
                    $workersData[(int)$workerRow['id_usuario']] = $workerRow;
                }

                if (count($workersData) !== count($workerIds)) {
                    $pdo->rollBack();
                    $redirectWithFeedback(['type' => 'danger', 'message' => 'No encontramos a todos los trabajadores seleccionados.']);
                }

                foreach ($workerIds as $workerId) {
                    $workerRow = $workersData[$workerId];
                    $workerEmail = trim((string)($workerRow['email'] ?? ''));
                    if ($workerEmail === '') {
                        $pdo->rollBack();
                        $redirectWithFeedback(['type' => 'danger', 'message' => 'Uno de los trabajadores no tiene correo electronico configurado.']);
                    }
                    if (isset($assignedUserIds[$workerId]) || isset($assignedEmails[strtolower($workerEmail)])) {
                        $pdo->rollBack();
                        $redirectWithFeedback(['type' => 'warning', 'message' => 'Alguno de los trabajadores ya tiene asignado este curso.']);
                    }
                }

                $updateSeat = $pdo->prepare(
                    'UPDATE checkout_capacitaciones
                     SET nombre = :nombre,
                         apellido = :apellido,
                         email = :email,
                         telefono = :telefono,
                         dni = NULL,
                         direccion = NULL,
                         ciudad = NULL,
                         provincia = NULL,
                         pais = NULL,
                         id_estado = :estado
                     WHERE id_capacitacion = :id'
                );

                $historyStmt = null;
                try {
                    $historyStmt = $pdo->prepare('INSERT INTO historico_estado_capacitaciones (id_capacitacion, id_estado, cambiado_en) VALUES (:id, :estado, NOW())');
                } catch (Throwable $historyException) {
                    $historyStmt = null;
                }

                $assignedStateId = 2;
                $availableQueue = $availableSeats;

                foreach ($workerIds as $workerId) {
                    $seatId = array_shift($availableQueue);
                    try {
                    if (!isset($stmtInsertAsigCap)) {
                        $stmtInsertAsigCap = $pdo->prepare("
                            INSERT INTO asignaciones_cursos
                                (id_asignado, id_asignado_por, id_curso, tipo_curso, id_checkout_capacitacion, id_checkout_certificacion, id_estado, observaciones)
                            VALUES
                                (:id_asignado, :id_asignado_por, :id_curso, 'capacitacion', :id_checkout_cap, NULL, :id_estado, NULL)
                        ");
                    }
                    $stmtInsertAsigCap->execute([
                        ':id_asignado'      => $workerId,
                        ':id_asignado_por'  => $userId,      // RRHH logueado
                        ':id_curso'         => $courseId,
                        ':id_checkout_cap'  => $seatId,      // la fila de checkout_capacitaciones que acabás de actualizar
                        ':id_estado'        => $assignedStateId, // 2 en tu código
                    ]);
                } catch (Throwable $eAsigCap) {
                    error_log('mis_cursos insert asignacion CAP: ' . $eAsigCap->getMessage());
                }
                    if ($seatId === null) {
                        break;
                    }
                    $workerRow = $workersData[$workerId];
                    $workerName = trim((string)($workerRow['nombre'] ?? ''));
                    $workerLastname = trim((string)($workerRow['apellido'] ?? ''));
                    $workerEmail = trim((string)($workerRow['email'] ?? ''));
                    $workerPhone = trim((string)($workerRow['telefono'] ?? ''));

                    $updateSeat->execute([
                        ':nombre' => $workerName !== '' ? $workerName : null,
                        ':apellido' => $workerLastname !== '' ? $workerLastname : null,
                        ':email' => $workerEmail,
                        ':telefono' => $workerPhone !== '' ? $workerPhone : null,
                        ':estado' => $assignedStateId,
                        ':id' => $seatId,
                    ]);

                    if ($historyStmt !== null) {
                        try {
                            $historyStmt->execute([
                                ':id' => $seatId,
                                ':estado' => $assignedStateId,
                            ]);
                        } catch (Throwable $historyError) {
                            error_log('mis_cursos history insert: ' . $historyError->getMessage());
                        }
                    }
                }

                $pdo->commit();

                $assignedTotal = count($workerIds);
                $redirectWithFeedback([
                    'type' => 'success',
                    'message' => $assignedTotal === 1
                        ? 'Se asigno 1 trabajador al curso.'
                        : 'Se asignaron ' . $assignedTotal . ' trabajadores al curso.'
                ]);
            } catch (Throwable $exception) {
                if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('mis_cursos assign_cap: ' . $exception->getMessage());
                $redirectWithFeedback(['type' => 'danger', 'message' => 'No pudimos asignar el curso. Intentalo nuevamente.']);
            }
           } elseif ($assignmentType === 'cert') {
            try {
                $pdo = getPdo();
                $pdo->beginTransaction();

                // 1) Bloquear "cupos" de certificaciones para este curso del usuario (como en CAP pero en checkout_certificaciones)
                $seatLockSql = <<<'SQL'
SELECT
    ccert.id_certificacion,
    ccert.email,
    ccert.id_estado,
    u.id_usuario AS assigned_user_id
FROM checkout_certificaciones ccert
LEFT JOIN usuarios u ON u.email = ccert.email
WHERE ccert.creado_por = :usuario AND ccert.id_curso = :curso
FOR UPDATE
SQL;
                $stmtSeats = $pdo->prepare($seatLockSql);
                $stmtSeats->bindValue(':usuario', $userId, PDO::PARAM_INT);
                $stmtSeats->bindValue(':curso', $courseId, PDO::PARAM_INT);
                $stmtSeats->execute();

                $seatRows = $stmtSeats->fetchAll(PDO::FETCH_ASSOC);
                if (empty($seatRows)) {
                    $pdo->rollBack();
                    $redirectWithFeedback(['type' => 'danger', 'message' => 'No se encontraron certificaciones para el curso seleccionado.']);
                }

                $availableSeats = [];
                $assignedUserIds = [];
                $assignedEmails = [];
                foreach ($seatRows as $seatRow) {
                    $seatId = (int)($seatRow['id_certificacion'] ?? 0);
                    if ($seatId <= 0) continue;
                    $email = trim((string)($seatRow['email'] ?? ''));
                    if ($email === '') {
                        $availableSeats[] = $seatId; // cupo sin asignar
                    } else {
                        $assignedEmails[strtolower($email)] = true;
                        if ($seatRow['assigned_user_id'] !== null) {
                            $assignedUserIds[(int)$seatRow['assigned_user_id']] = true;
                        }
                    }
                }

                if (empty($availableSeats)) {
                    $pdo->rollBack();
                    $redirectWithFeedback(['type' => 'danger', 'message' => 'No quedan cupos disponibles para esta certificación.']);
                }

                if (count($workerIds) > count($availableSeats)) {
                    $pdo->rollBack();
                    $redirectWithFeedback(['type' => 'warning', 'message' => 'Seleccionaste más trabajadores que cupos disponibles.']);
                }

                // 2) Validar que los trabajadores pertenezcan a la empresa
                $placeholders = implode(',', array_fill(0, count($workerIds), '?'));
                $stmtMembership = $pdo->prepare('SELECT id_trabajador FROM empresa_trabajadores WHERE id_empresa = ? AND id_trabajador IN (' . $placeholders . ')');
                $membershipParams = array_merge([$userId], $workerIds);
                $stmtMembership->execute($membershipParams);
                $validMembers = array_map('intval', $stmtMembership->fetchAll(PDO::FETCH_COLUMN));
                $missingWorkers = array_diff($workerIds, $validMembers);
                if (!empty($missingWorkers)) {
                    $pdo->rollBack();
                    $redirectWithFeedback(['type' => 'danger', 'message' => 'Algunos trabajadores seleccionados no pertenecen a tu empresa.']);
                }

                // 3) Traer datos de los trabajadores
                $stmtWorkersData = $pdo->prepare('SELECT id_usuario, nombre, apellido, email, telefono FROM usuarios WHERE id_usuario IN (' . $placeholders . ')');
                $stmtWorkersData->execute($workerIds);
                $workersData = [];
                while ($workerRow = $stmtWorkersData->fetch(PDO::FETCH_ASSOC)) {
                    $workersData[(int)$workerRow['id_usuario']] = $workerRow;
                }
                if (count($workersData) !== count($workerIds)) {
                    $pdo->rollBack();
                    $redirectWithFeedback(['type' => 'danger', 'message' => 'No encontramos a todos los trabajadores seleccionados.']);
                }

                // 4) Evitar duplicados (si ya están asignados a alguna fila)
                foreach ($workerIds as $workerId) {
                    $workerRow = $workersData[$workerId];
                    $workerEmail = trim((string)($workerRow['email'] ?? ''));
                    if ($workerEmail === '') {
                        $pdo->rollBack();
                        $redirectWithFeedback(['type' => 'danger', 'message' => 'Uno de los trabajadores no tiene correo electrónico configurado.']);
                    }
                    if (isset($assignedUserIds[$workerId]) || isset($assignedEmails[strtolower($workerEmail)])) {
                        $pdo->rollBack();
                        $redirectWithFeedback(['type' => 'warning', 'message' => 'Alguno de los trabajadores ya tiene asignada esta certificación.']);
                    }
                }

                // 5) Validar/recibir PDF (opcional, pero si viene debe ser PDF)
                $uploadedPdf = $_FILES['cert_pdf'] ?? null;
                $pdfIsProvided = is_array($uploadedPdf) && ($uploadedPdf['error'] === UPLOAD_ERR_OK) && (int)$uploadedPdf['size'] > 0;
                $pdfMime = null;
                $pdfOrigName = null;
                $pdfTempPath = null;

                if ($pdfIsProvided) {
                    $pdfOrigName = (string)$uploadedPdf['name'];
                    $pdfTempPath = (string)$uploadedPdf['tmp_name'];
                    $size = (int)$uploadedPdf['size'];
                    if ($size > 10 * 1024 * 1024) { // 10 MB
                        $pdo->rollBack();
                        $redirectWithFeedback(['type' => 'danger', 'message' => 'El PDF supera el tamaño máximo permitido (10MB).']);
                    }
                    // Chequeo MIME real
                    $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
                    $detected = $finfo ? finfo_file($finfo, $pdfTempPath) : null;
                    if ($finfo) { finfo_close($finfo); }
                    $pdfMime = $detected ?: ($uploadedPdf['type'] ?? '');
                    if (stripos($pdfMime, 'pdf') === false) {
                        $pdo->rollBack();
                        $redirectWithFeedback(['type' => 'danger', 'message' => 'El archivo subido debe ser un PDF.']);
                    }
                }

                // 6) Preparar UPDATE y (si existe) histórico de estados
                $updateSeat = $pdo->prepare(
                    'UPDATE checkout_certificaciones
                     SET nombre = :nombre,
                         apellido = :apellido,
                         email = :email,
                         telefono = :telefono,
                         id_estado = :estado,
                         pdf_path = :pdf_path,
                         pdf_nombre = :pdf_nombre,
                         pdf_mime = :pdf_mime
                     WHERE id_certificacion = :id'
                );

                $historyStmt = null;
                try {
                    $historyStmt = $pdo->prepare('INSERT INTO historico_estado_certificaciones (id_certificacion, id_estado, cambiado_en) VALUES (:id, :estado, NOW())');
                } catch (Throwable $historyException) {
                    $historyStmt = null; // si la tabla no existe, seguimos igual
                }

                $assignedStateId = 2; // por ejemplo "asignado/pendiente"
                $availableQueue = $availableSeats;

                // Preparar carpeta de subidas
                $baseDir = __DIR__ . '/uploads/certificaciones/' . $userId;
                if (!is_dir($baseDir)) {
                    @mkdir($baseDir, 0775, true);
                }
                $firstDestAbs = null; // guardamos el primer destino para copiar a los demás

                foreach ($workerIds as $workerId) {
                    // Registrar en asignaciones_cursos (CERT)
                    try {
                        if (!isset($stmtInsertAsigCert)) {
                            $stmtInsertAsigCert = $pdo->prepare("
                                INSERT INTO asignaciones_cursos
                                    (id_asignado, id_asignado_por, id_curso, tipo_curso, id_checkout_capacitacion, id_checkout_certificacion, id_estado, observaciones)
                                VALUES
                                    (:id_asignado, :id_asignado_por, :id_curso, 'certificacion', NULL, :id_checkout_cert, :id_estado, NULL)
                            ");
                        }
                        $stmtInsertAsigCert->execute([
                            ':id_asignado'       => $workerId,
                            ':id_asignado_por'   => $userId,         // RRHH logueado
                            ':id_curso'          => $courseId,
                            ':id_checkout_cert'  => $seatId,         // la fila de checkout_certificaciones que acabás de actualizar
                            ':id_estado'         => $assignedStateId, // 2 en tu código
                        ]);
                    } catch (Throwable $eAsigCert) {
                        error_log('mis_cursos insert asignacion CERT: ' . $eAsigCert->getMessage());
                    }

                    $seatId = array_shift($availableQueue);
                    if ($seatId === null) break;

                    $workerRow = $workersData[$workerId];
                    $workerName = trim((string)($workerRow['nombre'] ?? ''));
                    $workerLastname = trim((string)($workerRow['apellido'] ?? ''));
                    $workerEmail = trim((string)($workerRow['email'] ?? ''));
                    $workerPhone = trim((string)($workerRow['telefono'] ?? ''));

                    // Guardar PDF por cada asiento (si vino)
                    $destRel = null;
                    $destAbs = null;
                    $fileName = null;
                    $fileMime = null;

                    if ($pdfIsProvided) {
                        $ts = time();
                        $fileName = 'cert_' . $seatId . '_' . $ts . '.pdf';
                        $destAbs = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR . $fileName;
                        $destRel = 'uploads/certificaciones/' . $userId . '/' . $fileName;
                        $fileMime = $pdfMime;

                        if ($firstDestAbs === null) {
                            // mover el archivo original al primero
                            if (!@move_uploaded_file($pdfTempPath, $destAbs)) {
                                $pdo->rollBack();
                                $redirectWithFeedback(['type' => 'danger', 'message' => 'No se pudo guardar el PDF.']);
                            }
                            $firstDestAbs = $destAbs;
                        } else {
                            // copiar desde el primero a este destino
                            if (!@copy($firstDestAbs, $destAbs)) {
                                $pdo->rollBack();
                                $redirectWithFeedback(['type' => 'danger', 'message' => 'No se pudo duplicar el PDF para todos los asignados.']);
                            }
                        }
                    }

                    $updateSeat->execute([
                        ':nombre'     => $workerName !== '' ? $workerName : null,
                        ':apellido'   => $workerLastname !== '' ? $workerLastname : null,
                        ':email'      => $workerEmail,
                        ':telefono'   => $workerPhone !== '' ? $workerPhone : null,
                        ':estado'     => $assignedStateId,
                        ':pdf_path'   => $destRel,
                        ':pdf_nombre' => $pdfIsProvided ? $pdfOrigName : null,
                        ':pdf_mime'   => $pdfIsProvided ? $fileMime : null,
                        ':id'         => $seatId,
                    ]);

                    if ($historyStmt !== null) {
                        try {
                            $historyStmt->execute([
                                ':id' => $seatId,
                                ':estado' => $assignedStateId,
                            ]);
                        } catch (Throwable $historyError) {
                            error_log('mis_cursos history cert insert: ' . $historyError->getMessage());
                        }
                    }
                }

                $pdo->commit();

                $assignedTotal = count($workerIds);
                $redirectWithFeedback([
                    'type' => 'success',
                    'message' => $assignedTotal === 1
                        ? 'Se asignó 1 trabajador a la certificación.'
                        : 'Se asignaron ' . $assignedTotal . ' trabajadores a la certificación.'
                ]);
            } catch (Throwable $exception) {
                if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('mis_cursos assign_cert: ' . $exception->getMessage());
                $redirectWithFeedback(['type' => 'danger', 'message' => 'No pudimos asignar la certificación. Intentalo nuevamente.']);
            }
        } elseif ($legacyItemId !== null) {
            try {
                $pdo = getPdo();
                $pdo->beginTransaction();

                $stmtItem = $pdo->prepare(
                    'SELECT ci.id_item, ci.id_curso, ci.id_modalidad, ci.cantidad
                     FROM compra_items ci
                     INNER JOIN compras c ON c.id_compra = ci.id_compra
                     WHERE ci.id_item = :item AND c.id_usuario = :usuario AND c.estado = :estado
                     LIMIT 1 FOR UPDATE'
                );
                $stmtItem->bindValue(':item', $legacyItemId, PDO::PARAM_INT);
                $stmtItem->bindValue(':usuario', $userId, PDO::PARAM_INT);
                $stmtItem->bindValue(':estado', 'pagada', PDO::PARAM_STR);
                $stmtItem->execute();
                $itemData = $stmtItem->fetch(PDO::FETCH_ASSOC);

                if (!$itemData) {
                    $pdo->rollBack();
                    $redirectWithFeedback(['type' => 'danger', 'message' => 'No se encontro el curso seleccionado.']);
                }

                $stmtAssignments = $pdo->prepare('SELECT id_usuario FROM inscripciones WHERE id_item_compra = :item FOR UPDATE');
                $stmtAssignments->bindValue(':item', $legacyItemId, PDO::PARAM_INT);
                $stmtAssignments->execute();
                $currentAssignments = array_map('intval', $stmtAssignments->fetchAll(PDO::FETCH_COLUMN));
                $assignedCount = count($currentAssignments);
                $available = max(0, (int)$itemData['cantidad'] - $assignedCount);

                if ($available <= 0) {
                    $pdo->rollBack();
                    $redirectWithFeedback(['type' => 'danger', 'message' => 'No quedan cupos disponibles para este curso.']);
                }

                if (count($workerIds) > $available) {
                    $pdo->rollBack();
                    $redirectWithFeedback(['type' => 'warning', 'message' => 'Seleccionaste mas trabajadores que cupos disponibles.']);
                }

                $placeholders = implode(',', array_fill(0, count($workerIds), '?'));
                $stmtMembership = $pdo->prepare('SELECT id_trabajador FROM empresa_trabajadores WHERE id_empresa = ? AND id_trabajador IN (' . $placeholders . ')');
                $membershipParams = array_merge([$userId], $workerIds);
                $stmtMembership->execute($membershipParams);
                $validMembers = array_map('intval', $stmtMembership->fetchAll(PDO::FETCH_COLUMN));
                $missingWorkers = array_diff($workerIds, $validMembers);
                if (!empty($missingWorkers)) {
                    $pdo->rollBack();
                    $redirectWithFeedback(['type' => 'danger', 'message' => 'Algunos trabajadores seleccionados no pertenecen a tu empresa.']);
                }

                $alreadyAssigned = array_intersect($workerIds, $currentAssignments);
                if (!empty($alreadyAssigned)) {
                    $pdo->rollBack();
                    $redirectWithFeedback(['type' => 'warning', 'message' => 'Alguno de los trabajadores ya tiene asignado este curso.']);
                }

                $insert = $pdo->prepare(
                    'INSERT INTO inscripciones (id_usuario, id_curso, id_modalidad, id_item_compra)
                     VALUES (:usuario, :curso, :modalidad, :item)'
                );
                $insert->bindValue(':curso', (int)$itemData['id_curso'], PDO::PARAM_INT);
                if ($itemData['id_modalidad'] !== null) {
                    $insert->bindValue(':modalidad', (int)$itemData['id_modalidad'], PDO::PARAM_INT);
                } else {
                    $insert->bindValue(':modalidad', null, PDO::PARAM_NULL);
                }
                $insert->bindValue(':item', $legacyItemId, PDO::PARAM_INT);

                foreach ($workerIds as $workerId) {
                    $insert->bindValue(':usuario', $workerId, PDO::PARAM_INT);
                    $insert->execute();
                }

                $pdo->commit();

                $assignedTotal = count($workerIds);
                $redirectWithFeedback([
                    'type' => 'success',
                    'message' => $assignedTotal === 1
                        ? 'Se asigno 1 trabajador al curso.'
                        : 'Se asignaron ' . $assignedTotal . ' trabajadores al curso.'
                ]);
            } catch (Throwable $exception) {
                if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('mis_cursos assign_workers: ' . $exception->getMessage());
                $redirectWithFeedback(['type' => 'danger', 'message' => 'No pudimos asignar el curso. Intentalo nuevamente.']);
            }
        } else {
            $redirectWithFeedback(['type' => 'danger', 'message' => 'El curso seleccionado no es valido.']);
        }
    }

    $redirectWithFeedback(['type' => 'danger', 'message' => 'Accion no valida.']);

}

$misCursosAlert = $_SESSION['mis_cursos_alert'] ?? null;
if ($misCursosAlert !== null) {
    unset($_SESSION['mis_cursos_alert']);
}

$page_title = 'Mis cursos | Instituto de Formacion';
$page_description = 'Cursos disponibles para tu cuenta.';
$page_styles = '<link rel="stylesheet" href="assets/styles/style_configuracion.css">';

$statusLabels = [
    'inscripto' => 'Inscripto',
    'en_curso' => 'En curso',
    'completado' => 'Completado',
    'vencido' => 'Vencido',
    'cancelado' => 'Cancelado',
    'pendiente' => 'Pendiente',
    'aprobado' => 'Aprobado',
    'pagado' => 'Pagado',
    'rechazado' => 'Rechazado',
];

$statusClasses = [
    'inscripto' => 'bg-primary',
    'en_curso' => 'bg-info text-dark',
    'completado' => 'bg-success',
    'vencido' => 'bg-warning text-dark',
    'cancelado' => 'bg-danger',
    'pendiente' => 'bg-warning text-dark',
    'aprobado' => 'bg-success',
    'pagado' => 'bg-success',
    'rechazado' => 'bg-danger',
];

$cursosComprados = [];
$workersOptions = [];
$errorMessage = null;

try {
    $pdo = getPdo();

    $tableExists = static function (PDO $pdo, string $table): bool {
        try {
            $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table LIMIT 1");
            $stmt->bindValue(':table', $table, PDO::PARAM_STR);
            $stmt->execute();
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $tableException) {
            error_log('mis_cursos table check ' . $table . ': ' . $tableException->getMessage());
            return false;
        }
    };

    $inscripcionesAvailable = $tableExists($pdo, 'inscripciones');
    $comprasAvailable = $tableExists($pdo, 'compras');
    $compraItemsAvailable = $tableExists($pdo, 'compra_items');
    $checkoutCapacitacionesAvailable = $tableExists($pdo, 'checkout_capacitaciones');
    $checkoutCertificacionesAvailable = $tableExists($pdo, 'checkout_certificaciones');
    $checkoutPagosAvailable = $tableExists($pdo, 'checkout_pagos');
    $estadosInscripcionesAvailable = $tableExists($pdo, 'estados_inscripciones');
    $asignacionesCursosAvailable = $tableExists($pdo, 'asignaciones_cursos');
    $cursoModalidadAvailable = $tableExists($pdo, 'curso_modalidad');
    $modalidadesAvailable = $tableExists($pdo, 'modalidades');


    if ($isHrManager) {
        $courses = [];

        if ($checkoutCapacitacionesAvailable) {
            $capModalidadSelect = ($cursoModalidadAvailable && $modalidadesAvailable)
                ? 'mods.modalidad_resumen'
                : 'NULL AS modalidad_resumen';
            $capModalidadJoin = ($cursoModalidadAvailable && $modalidadesAvailable)
                ? <<<SQL
LEFT JOIN (
    SELECT cm.id_curso, GROUP_CONCAT(DISTINCT m.nombre_modalidad ORDER BY m.nombre_modalidad SEPARATOR ' / ') AS modalidad_resumen
    FROM curso_modalidad cm
    INNER JOIN modalidades m ON m.id_modalidad = cm.id_modalidad
    GROUP BY cm.id_curso
) AS mods ON mods.id_curso = cc.id_curso
SQL
                : '';

            $capPagosSelect = $checkoutPagosAvailable
                ? 'pago.last_pago_id, pago.last_pago_estado'
                : 'NULL AS last_pago_id, NULL AS last_pago_estado';
            $capPagosJoin = $checkoutPagosAvailable
                ? <<<SQL
LEFT JOIN (
    SELECT
        id_capacitacion,
        MAX(id_pago) AS last_pago_id,
        SUBSTRING_INDEX(GROUP_CONCAT(estado ORDER BY id_pago DESC SEPARATOR ','), ',', 1) AS last_pago_estado
    FROM checkout_pagos
    WHERE id_capacitacion IS NOT NULL
    GROUP BY id_capacitacion
) AS pago ON pago.id_capacitacion = cc.id_capacitacion
SQL
                : '';

            $sqlCapacitaciones = <<<SQL
SELECT
    cc.id_capacitacion,
    cc.id_curso,
    cc.precio_total,
    cc.moneda,
    cc.nombre,
    cc.apellido,
    cc.email,
    cc.telefono,
    cc.id_estado,
    cc.creado_en,
    c.nombre_curso,
    {$capModalidadSelect},
    est.nombre_estado AS estado_checkout,
    {$capPagosSelect},
    u.id_usuario AS assigned_user_id,
    u.nombre AS assigned_nombre,
    u.apellido AS assigned_apellido
FROM checkout_capacitaciones cc
LEFT JOIN cursos c ON c.id_curso = cc.id_curso
{$capModalidadJoin}
LEFT JOIN estados_inscripciones est ON est.id_estado = cc.id_estado
{$capPagosJoin}
LEFT JOIN usuarios u ON u.email = cc.email
WHERE cc.creado_por = :usuario
ORDER BY cc.id_curso ASC, cc.creado_en ASC, cc.id_capacitacion ASC
SQL;
            $stmtCapacitaciones = $pdo->prepare($sqlCapacitaciones);
            $stmtCapacitaciones->bindValue(':usuario', $userId, PDO::PARAM_INT);
            $stmtCapacitaciones->execute();

            while ($seat = $stmtCapacitaciones->fetch(PDO::FETCH_ASSOC)) {
                $courseId = (int)($seat['id_curso'] ?? 0);
                if ($courseId <= 0) {
                    continue;
                }
                $courseKey = 'cap-course-' . $courseId;
                $courseName = trim((string)($seat['nombre_curso'] ?? ''));
                if ($courseName === '') {
                    $courseName = 'Curso #' . $courseId;
                }
                $modalidadResumen = $seat['modalidad_resumen'] ?? null;

                if (!isset($courses[$courseKey])) {
                    $courses[$courseKey] = [
                        'id_item' => null,
                        'id_curso' => $courseId,
                        'tipo_curso' => 'capacitacion',
                        'nombre_curso' => $courseName,
                        'nombre_modalidad' => $modalidadResumen,
                        'pagado_en' => null,
                        'pagado_en_formatted' => null,
                        'moneda' => null,
                        'precio_unitario' => null,
                        'cantidad' => 0,
                        'inscripcion' => null,
                        'asignaciones' => [],
                        'asignados' => 0,
                        'disponibles' => 0,
                        'can_assign' => false,
                        'purchase_items' => [],
                        'registros_checkout' => [],
                    ];
                } elseif ($courses[$courseKey]['nombre_modalidad'] === null && $modalidadResumen !== null) {
                    $courses[$courseKey]['nombre_modalidad'] = $modalidadResumen;
                }

                $course =& $courses[$courseKey];
                $purchaseKey = 'cap-' . $courseId;

                if (!isset($course['purchase_items'][$purchaseKey])) {
                    $course['purchase_items'][$purchaseKey] = [
                        'id_item' => $purchaseKey,
                        'id_curso' => $courseId,
                        'tipo_compra' => 'capacitacion',
                        'nombre_curso' => $course['nombre_curso'],
                        'nombre_modalidad' => $course['nombre_modalidad'],
                        'pagado_en' => null,
                        'pagado_en_formatted' => null,
                        'moneda' => null,
                        'precio_unitario' => null,
                        'cantidad' => 0,
                        'asignaciones' => [],
                        'asignados' => 0,
                        'disponibles' => 0,
                        'can_assign' => true,
                    ];
                }

                $purchaseItem =& $course['purchase_items'][$purchaseKey];

                $course['cantidad']++;
                $purchaseItem['cantidad']++;

                if ($purchaseItem['precio_unitario'] === null && $seat['precio_total'] !== null) {
                    $purchaseItem['precio_unitario'] = (float)$seat['precio_total'];
                }
                if ($purchaseItem['moneda'] === null && $seat['moneda'] !== null) {
                    $purchaseItem['moneda'] = (string)$seat['moneda'];
                }
                if ($course['moneda'] === null && $purchaseItem['moneda'] !== null) {
                    $course['moneda'] = $purchaseItem['moneda'];
                }
                if ($course['precio_unitario'] === null && $purchaseItem['precio_unitario'] !== null) {
                    $course['precio_unitario'] = $purchaseItem['precio_unitario'];
                }

                $registroCheckout = [
                    'tipo' => 'capacitacion',
                    'id_registro' => (int)($seat['id_capacitacion'] ?? 0),
                    'id_curso' => $courseId,
                    'estado' => (int)($seat['id_estado'] ?? 0),
                    'pago_id' => isset($seat['last_pago_id']) ? (int)$seat['last_pago_id'] : 0,
                    'pago_estado' => strtolower((string)($seat['last_pago_estado'] ?? '')),
                ];
                if ($registroCheckout['id_registro'] > 0) {
                    $course['registros_checkout'][] = $registroCheckout;
                }

                $createdAt = $seat['creado_en'] ?? null;
                if ($createdAt !== null) {
                    try {
                        $createdDate = new DateTimeImmutable($createdAt);
                        $formattedDate = $createdDate->format('d/m/Y H:i');
                        $timestamp = (int)$createdDate->format('U');
                        $purchaseItemTimestamp = $purchaseItem['pagado_en'] !== null ? strtotime((string)$purchaseItem['pagado_en']) : null;
                        if ($purchaseItemTimestamp === null || $timestamp > $purchaseItemTimestamp) {
                            $purchaseItem['pagado_en'] = $createdDate->format('Y-m-d H:i:s');
                            $purchaseItem['pagado_en_formatted'] = $formattedDate;
                        }
                        $courseTimestamp = $course['pagado_en'] !== null ? strtotime((string)$course['pagado_en']) : null;
                        if ($courseTimestamp === null || $timestamp > $courseTimestamp) {
                            $course['pagado_en'] = $createdDate->format('Y-m-d H:i:s');
                            $course['pagado_en_formatted'] = $formattedDate;
                        }
                    } catch (Throwable $dateException) {
                        $purchaseItem['pagado_en'] = $createdAt;
                        $purchaseItem['pagado_en_formatted'] = $createdAt;
                        if ($course['pagado_en'] === null) {
                            $course['pagado_en'] = $createdAt;
                            $course['pagado_en_formatted'] = $createdAt;
                        }
                    }
                }

                $email = trim((string)($seat['email'] ?? ''));
                if ($email === '') {
                    $course['disponibles']++;
                    $purchaseItem['disponibles']++;
                    $course['can_assign'] = true;
                    $purchaseItem['can_assign'] = true;
                } else {
                    $stateKey = strtolower((string)($seat['estado_checkout'] ?? ''));
                    $stateLabel = $statusLabels[$stateKey] ?? ucwords(str_replace('_', ' ', $stateKey));
                    $stateClass = $statusClasses[$stateKey] ?? 'bg-secondary';

                    $assignmentData = [
                        'id_capacitacion' => (int)$seat['id_capacitacion'],
                        'id_usuario' => $seat['assigned_user_id'] !== null ? (int)$seat['assigned_user_id'] : null,
                        'nombre' => $seat['nombre'] ?? $seat['assigned_nombre'] ?? '',
                        'apellido' => $seat['apellido'] ?? $seat['assigned_apellido'] ?? '',
                        'email' => $email,
                        'estado' => $stateLabel,
                        'estado_key' => $stateKey,
                        'clase' => $stateClass,
                        'progreso' => null,
                        'id_item_compra' => (int)$seat['id_capacitacion'],
                    ];

                    $purchaseItem['asignaciones'][] = $assignmentData;
                    $purchaseItem['asignados']++;
                    $course['asignaciones'][] = $assignmentData;
                    $course['asignados']++;
                }
            }
        }

        if ($checkoutCertificacionesAvailable) {
            $certModalidadSelect = ($cursoModalidadAvailable && $modalidadesAvailable)
                ? 'mods.modalidad_resumen'
                : 'NULL AS modalidad_resumen';
            $certModalidadJoin = ($cursoModalidadAvailable && $modalidadesAvailable)
                ? <<<SQL
LEFT JOIN (
    SELECT cm.id_curso, GROUP_CONCAT(DISTINCT m.nombre_modalidad ORDER BY m.nombre_modalidad SEPARATOR ' / ') AS modalidad_resumen
    FROM curso_modalidad cm
    INNER JOIN modalidades m ON m.id_modalidad = cm.id_modalidad
    GROUP BY cm.id_curso
) AS mods ON mods.id_curso = ccert.id_curso
SQL
                : '';

            $certPagosSelect = $checkoutPagosAvailable
                ? 'pago.last_pago_id, pago.last_pago_estado'
                : 'NULL AS last_pago_id, NULL AS last_pago_estado';
            $certPagosJoin = $checkoutPagosAvailable
                ? <<<SQL
LEFT JOIN (
    SELECT
        id_certificacion,
        MAX(id_pago) AS last_pago_id,
        SUBSTRING_INDEX(GROUP_CONCAT(estado ORDER BY id_pago DESC SEPARATOR ','), ',', 1) AS last_pago_estado
    FROM checkout_pagos
    WHERE id_certificacion IS NOT NULL
    GROUP BY id_certificacion
) AS pago ON pago.id_certificacion = ccert.id_certificacion
SQL
                : '';

            $sqlCertificaciones = <<<SQL
            SELECT
                ccert.id_certificacion,
                ccert.id_curso,
                ccert.precio_total,
                ccert.moneda,
                ccert.creado_en,
                ccert.nombre,
                ccert.apellido,
                ccert.email,
                ccert.telefono,
                ccert.id_estado,
                ccert.pdf_path,
                ccert.pdf_nombre,
                ccert.pdf_mime,
                cursos.nombre_curso,
                {$certModalidadSelect},
                est.nombre_estado AS estado_checkout,
                {$certPagosSelect},
                u.id_usuario AS assigned_user_id,
                u.nombre AS assigned_nombre,
                u.apellido AS assigned_apellido
            FROM checkout_certificaciones ccert
            LEFT JOIN cursos ON cursos.id_curso = ccert.id_curso
{$certModalidadJoin}
            LEFT JOIN estados_inscripciones est ON est.id_estado = ccert.id_estado
{$certPagosJoin}
            LEFT JOIN usuarios u ON u.email = ccert.email
            WHERE ccert.creado_por = :usuario
            ORDER BY ccert.id_curso ASC, ccert.creado_en ASC, ccert.id_certificacion ASC
SQL;
            $stmtCertificaciones = $pdo->prepare($sqlCertificaciones);
            $stmtCertificaciones->bindValue(':usuario', $userId, PDO::PARAM_INT);
            $stmtCertificaciones->execute();

            while ($cert = $stmtCertificaciones->fetch(PDO::FETCH_ASSOC)) {
                $courseId = (int)($cert['id_curso'] ?? 0);
                if ($courseId <= 0) {
                    continue;
                }
                $courseKey = 'cert-course-' . $courseId;
                $courseName = trim((string)($cert['nombre_curso'] ?? ''));
                if ($courseName === '') {
                    $courseName = 'Curso #' . $courseId;
                }
                $modalidadResumen = $cert['modalidad_resumen'] ?? null;

                if (!isset($courses[$courseKey])) {
                    $courses[$courseKey] = [
                        'id_item' => null,
                        'id_curso' => $courseId,
                        'tipo_curso' => 'certificacion',
                        'nombre_curso' => $courseName,
                        'nombre_modalidad' => $modalidadResumen,
                        'pagado_en' => null,
                        'pagado_en_formatted' => null,
                        'moneda' => null,
                        'precio_unitario' => null,
                        'cantidad' => 0,
                        'inscripcion' => null,
                        'asignaciones' => [],
                        'asignados' => 0,
                        'disponibles' => 0,
                        'can_assign' => false,
                        'purchase_items' => [],
                        'registros_checkout' => [],
                    ];
                } elseif ($courses[$courseKey]['nombre_modalidad'] === null && $modalidadResumen !== null) {
                    $courses[$courseKey]['nombre_modalidad'] = $modalidadResumen;
                }

                $course =& $courses[$courseKey];
                $purchaseKey = 'cert-' . $courseId;

                if (!isset($course['purchase_items'][$purchaseKey])) {
                    $course['purchase_items'][$purchaseKey] = [
                        'id_item' => $purchaseKey,
                        'id_curso' => $courseId,
                        'tipo_compra' => 'certificacion',
                        'nombre_curso' => $course['nombre_curso'],
                        'nombre_modalidad' => $course['nombre_modalidad'],
                        'pagado_en' => null,
                        'pagado_en_formatted' => null,
                        'moneda' => null,
                        'precio_unitario' => null,
                        'cantidad' => 0,
                        'asignaciones' => [],
                        'asignados' => 0,
                        'disponibles' => 0,
                        'can_assign' => false,
                    ];
                }

                $purchaseItem =& $course['purchase_items'][$purchaseKey];

                $course['cantidad']++;
                $purchaseItem['cantidad']++;

                if ($purchaseItem['precio_unitario'] === null && $cert['precio_total'] !== null) {
                    $purchaseItem['precio_unitario'] = (float)$cert['precio_total'];
                }
                if ($purchaseItem['moneda'] === null && $cert['moneda'] !== null) {
                    $purchaseItem['moneda'] = (string)$cert['moneda'];
                }
                if ($course['moneda'] === null && $purchaseItem['moneda'] !== null) {
                    $course['moneda'] = $purchaseItem['moneda'];
                }
                if ($course['precio_unitario'] === null && $purchaseItem['precio_unitario'] !== null) {
                    $course['precio_unitario'] = $purchaseItem['precio_unitario'];
                }

                $registroCheckout = [
                    'tipo' => 'certificacion',
                    'id_registro' => (int)($cert['id_certificacion'] ?? 0),
                    'id_curso' => $courseId,
                    'estado' => (int)($cert['id_estado'] ?? 0),
                    'pago_id' => isset($cert['last_pago_id']) ? (int)$cert['last_pago_id'] : 0,
                    'pago_estado' => strtolower((string)($cert['last_pago_estado'] ?? '')),
                ];
                if ($registroCheckout['id_registro'] > 0) {
                    $course['registros_checkout'][] = $registroCheckout;
                }

                $createdAt = $cert['creado_en'] ?? null;
                if ($createdAt !== null) {
                    try {
                        $createdDate = new DateTimeImmutable($createdAt);
                        $formattedDate = $createdDate->format('d/m/Y H:i');
                        $timestamp = (int)$createdDate->format('U');
                        $purchaseItemTimestamp = $purchaseItem['pagado_en'] !== null ? strtotime((string)$purchaseItem['pagado_en']) : null;
                        if ($purchaseItemTimestamp === null || $timestamp > $purchaseItemTimestamp) {
                            $purchaseItem['pagado_en'] = $createdDate->format('Y-m-d H:i:s');
                            $purchaseItem['pagado_en_formatted'] = $formattedDate;
                        }
                        $courseTimestamp = $course['pagado_en'] !== null ? strtotime((string)$course['pagado_en']) : null;
                        if ($courseTimestamp === null || $timestamp > $courseTimestamp) {
                            $course['pagado_en'] = $createdDate->format('Y-m-d H:i:s');
                            $course['pagado_en_formatted'] = $formattedDate;
                        }
                    } catch (Throwable $dateException) {
                        $purchaseItem['pagado_en'] = $createdAt;
                        $purchaseItem['pagado_en_formatted'] = $createdAt;
                        if ($course['pagado_en'] === null) {
                            $course['pagado_en'] = $createdAt;
                            $course['pagado_en_formatted'] = $createdAt;
                        }
                    }
                     $email = trim((string)($cert['email'] ?? ''));
                    if ($email === '') {
                        $course['disponibles']++;
                        $purchaseItem['disponibles']++;
                        // permitir asignar en certificaciones
                        $course['can_assign'] = true;
                        $purchaseItem['can_assign'] = true;
                    } else {
                        $stateKey = strtolower((string)($cert['estado_checkout'] ?? ''));
                        $stateLabel = $statusLabels[$stateKey] ?? ucwords(str_replace('_', ' ', $stateKey));
                        $stateClass = $statusClasses[$stateKey] ?? 'bg-secondary';

                        $assignmentData = [
                            'id_certificacion' => (int)$cert['id_certificacion'],
                            'id_usuario' => $cert['assigned_user_id'] !== null ? (int)$cert['assigned_user_id'] : null,
                            'nombre' => $cert['nombre'] ?? $cert['assigned_nombre'] ?? '',
                            'apellido' => $cert['apellido'] ?? $cert['assigned_apellido'] ?? '',
                            'email' => $email,
                            'estado' => $stateLabel,
                            'estado_key' => $stateKey,
                            'clase' => $stateClass,
                            'progreso' => null,
                            'id_item_compra' => (int)$cert['id_certificacion'],
                            'pdf_path' => $cert['pdf_path'] ?? null,
                            'pdf_nombre' => $cert['pdf_nombre'] ?? null,
                            'pdf_mime' => $cert['pdf_mime'] ?? null,
                            'id_estado' => isset($cert['id_estado']) ? (int)$cert['id_estado'] : null,
                            'puede_pagar' => isset($cert['id_estado']) && (int)$cert['id_estado'] === 2,
                        ];

                        $purchaseItem['asignaciones'][] = $assignmentData;
                        $purchaseItem['asignados']++;
                        $course['asignaciones'][] = $assignmentData;
                        $course['asignados']++;
                    }
                }
            }
        }

        if (!empty($courses)) {
            uasort($courses, static function (array $left, array $right): int {
                return strcmp(strtolower((string)($left['nombre_curso'] ?? '')), strtolower((string)($right['nombre_curso'] ?? '')));
            });
        }

        $stmtWorkers = $pdo->prepare(
            'SELECT u.id_usuario, u.nombre, u.apellido, u.email
             FROM empresa_trabajadores et
             INNER JOIN usuarios u ON u.id_usuario = et.id_trabajador
             WHERE et.id_empresa = ? AND u.id_permiso = 4
             ORDER BY u.nombre ASC, u.apellido ASC, u.email ASC'
        );
        $stmtWorkers->execute([$userId]);
        $workersOptions = $stmtWorkers->fetchAll(PDO::FETCH_ASSOC);

        foreach ($courses as &$course) {
            $courseDisponibles = (int)($course['disponibles'] ?? 0);
            $course['disponibles'] = $courseDisponibles;
            $course['asignados'] = (int)($course['asignados'] ?? 0);
            $course['cantidad'] = (int)($course['cantidad'] ?? 0);
            $courseHasSlots = !empty($workersOptions) && $courseDisponibles > 0 && in_array($course['tipo_curso'] ?? '', ['capacitacion','certificacion'], true);


            $course['can_assign'] = $courseHasSlots;

            foreach ($course['purchase_items'] as &$purchaseItem) {
                $purchaseItem['disponibles'] = (int)($purchaseItem['disponibles'] ?? 0);
                $purchaseItem['asignados'] = (int)($purchaseItem['asignados'] ?? 0);
                $purchaseItem['cantidad'] = (int)($purchaseItem['cantidad'] ?? 0);
                $purchaseItem['can_assign'] = $courseHasSlots && $purchaseItem['disponibles'] > 0;
            }
            unset($purchaseItem);

            $course['purchase_items'] = array_values($course['purchase_items']);
        }
        unset($course);

        $cursosComprados = array_values($courses);
    } else {
        if ($comprasAvailable && $compraItemsAvailable) {
            $items = [];

            $modalidadSelect = $modalidadesAvailable
                ? 'modalidades.nombre_modalidad'
                : 'NULL AS nombre_modalidad';
            $modalidadesJoin = $modalidadesAvailable
                ? "
                 LEFT JOIN modalidades ON modalidades.id_modalidad = ci.id_modalidad"
                : '';
            $selectBase = "SELECT
                    c.id_compra,
                    c.pagado_en,
                    c.moneda,
                    ci.id_item,
                    ci.cantidad,
                    ci.precio_unitario,
                    ci.titulo_snapshot,
                    cursos.nombre_curso,
                    {$modalidadSelect}";
            if ($inscripcionesAvailable) {
                $selectFields = $selectBase . ",
                    i.id_inscripcion,
                    i.estado AS inscripcion_estado,
                    i.progreso AS inscripcion_progreso";
                $joinInscripciones = " LEFT JOIN inscripciones i ON i.id_item_compra = ci.id_item AND i.id_usuario = c.id_usuario";
            } else {
                $selectFields = $selectBase . ",
                    NULL AS id_inscripcion,
                    NULL AS inscripcion_estado,
                    NULL AS inscripcion_progreso";
                $joinInscripciones = '';
            }

            $sqlUser = $selectFields . "
                 FROM compras c
                 INNER JOIN compra_items ci ON ci.id_compra = c.id_compra
                 INNER JOIN cursos ON cursos.id_curso = ci.id_curso{$modalidadesJoin}" . $joinInscripciones . "
                 WHERE c.id_usuario = :usuario
                   AND c.estado = :estado
                 ORDER BY c.pagado_en DESC, c.id_compra DESC, ci.id_item ASC";

            $stmt = $pdo->prepare($sqlUser);
            $stmt->bindValue(':usuario', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':estado', 'pagada', PDO::PARAM_STR);
            $stmt->execute();

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $itemId = (int)$row['id_item'];

                if (!isset($items[$itemId])) {
                    $formattedDate = null;
                    if (!empty($row['pagado_en'])) {
                        try {
                            $formattedDate = (new DateTimeImmutable($row['pagado_en']))->format('d/m/Y H:i');
                        } catch (Throwable $exception) {
                            $formattedDate = $row['pagado_en'];
                        }
                    }

                    $items[$itemId] = [
                        'id_item' => $itemId,
                        'nombre_curso' => $row['nombre_curso'] ?: $row['titulo_snapshot'],
                        'nombre_modalidad' => $row['nombre_modalidad'],
                        'pagado_en' => $row['pagado_en'],
                        'pagado_en_formatted' => $formattedDate,
                        'moneda' => $row['moneda'],
                        'precio_unitario' => (float)$row['precio_unitario'],
                        'cantidad' => (int)$row['cantidad'],
                        'inscripcion' => null,
                    ];
                }

                if ($inscripcionesAvailable && $items[$itemId]['inscripcion'] === null && !empty($row['inscripcion_estado'])) {
                    $stateKey = strtolower((string)$row['inscripcion_estado']);
                    $stateLabel = $statusLabels[$stateKey] ?? ucwords(str_replace('_', ' ', (string)$row['inscripcion_estado']));
                    $stateClass = $statusClasses[$stateKey] ?? 'bg-secondary';

                    $progress = null;
                    if ($row['inscripcion_progreso'] !== null) {
                        $progress = max(0, min(100, (int)($row['inscripcion_progreso'])));
                    }

                    $items[$itemId]['inscripcion'] = [
                        'id_inscripcion' => (int)$row['id_inscripcion'],
                        'estado' => $stateLabel,
                        'clase' => $stateClass,
                        'progreso' => $progress,
                    ];
                }
            }

            $cursosComprados = array_values($items);
        } else {
            $cursosComprados = [];
        }

        if ($checkoutCapacitacionesAvailable) {
            $capResumenSelect = ($cursoModalidadAvailable && $modalidadesAvailable)
                ? 'mods.modalidad_resumen'
                : 'NULL AS modalidad_resumen';
            $capResumenJoin = ($cursoModalidadAvailable && $modalidadesAvailable)
                ? <<<SQL
LEFT JOIN (
    SELECT cm.id_curso, GROUP_CONCAT(DISTINCT m.nombre_modalidad ORDER BY m.nombre_modalidad SEPARATOR ' / ') AS modalidad_resumen
      FROM curso_modalidad cm
      INNER JOIN modalidades m ON m.id_modalidad = cm.id_modalidad
     GROUP BY cm.id_curso
) AS mods ON mods.id_curso = cc.id_curso
SQL
                : '';

            $sqlCapPagadas = <<<SQL
SELECT
    cc.id_capacitacion,
    cc.id_curso,
    cc.creado_en,
    cc.id_estado,
    c.nombre_curso,
    {$capResumenSelect}
FROM checkout_capacitaciones cc
INNER JOIN cursos c ON c.id_curso = cc.id_curso
{$capResumenJoin}
WHERE cc.creado_por = :usuario
  AND cc.id_estado = 3
ORDER BY cc.creado_en DESC, cc.id_capacitacion DESC
SQL;

            $stmtCapPagadas = $pdo->prepare($sqlCapPagadas);
            $stmtCapPagadas->bindValue(':usuario', $userId, PDO::PARAM_INT);
            $stmtCapPagadas->execute();

            $capCursosKeys = [];
            while ($row = $stmtCapPagadas->fetch(PDO::FETCH_ASSOC)) {
                $rowId = (int)($row['id_capacitacion'] ?? 0);
                $rowCursoId = (int)($row['id_curso'] ?? 0);
                if ($rowId <= 0 || $rowCursoId <= 0) {
                    continue;
                }

                $entryKey = 'checkout-cap-' . $rowId;
                if (isset($capCursosKeys[$entryKey])) {
                    continue;
                }
                $capCursosKeys[$entryKey] = true;

                $fechaFmt = null;
                if (!empty($row['creado_en'])) {
                    try {
                        $fechaFmt = (new DateTimeImmutable((string)$row['creado_en']))->format('d/m/Y H:i');
                    } catch (Throwable $capPaidDateException) {
                        $fechaFmt = $row['creado_en'];
                    }
                }

                $cursosComprados[] = [
                    'id_item'             => -abs($rowId),
                    'id_curso'            => $rowCursoId,
                    'tipo_curso'          => 'capacitacion',
                    'nombre_curso'        => $row['nombre_curso'] ?: ('Curso #' . $rowCursoId),
                    'nombre_modalidad'    => $row['modalidad_resumen'] ?? null,
                    'pagado_en'           => $row['creado_en'] ?? null,
                    'pagado_en_formatted' => $fechaFmt,
                    'moneda'              => null,
                    'precio_unitario'     => null,
                    'cantidad'            => 1,
                    'inscripcion'         => [
                        'estado'   => 'Pago aprobado',
                        'clase'    => 'bg-success',
                        'progreso' => null,
                    ],
                    'origen'              => 'checkout_capacitacion',
                ];
            }
        }

        if (!empty($cursosComprados)) {
            usort($cursosComprados, static function (array $left, array $right): int {
                $leftDate = (string)($left['pagado_en'] ?? '');
                $rightDate = (string)($right['pagado_en'] ?? '');
                return strcmp($rightDate, $leftDate);
            });
        }
    }
    $asignadas = [];
    if ($asignacionesCursosAvailable) {
        $sqlAsignadas = <<<'SQL'
        SELECT
            a.id_asignacion,
            a.id_curso,
            a.tipo_curso,
            a.creado_en,
            c.nombre_curso
        FROM asignaciones_cursos a
        INNER JOIN cursos c ON c.id_curso = a.id_curso
        WHERE a.id_asignado = :usuario
          AND a.id_estado IN (1,2)
        ORDER BY a.creado_en DESC, a.id_asignacion DESC
SQL;
        $stmtAsignadas = $pdo->prepare($sqlAsignadas);
        $stmtAsignadas->bindValue(':usuario', $userId, PDO::PARAM_INT);
        $stmtAsignadas->execute();

        while ($row = $stmtAsignadas->fetch(PDO::FETCH_ASSOC)) {
            $fechaFmt = null;
            if (!empty($row['creado_en'])) {
                try {
                    $fechaFmt = (new DateTimeImmutable($row['creado_en']))->format('d/m/Y H:i');
                } catch (Throwable $e) {
                    $fechaFmt = $row['creado_en'];
                }
            }

            $asignadas[] = [
                'id_item'             => (int)$row['id_asignacion'],
                'id_curso'            => (int)$row['id_curso'],
                'tipo_curso'          => (string)$row['tipo_curso'],
                'nombre_curso'        => $row['nombre_curso'] ?: ('Curso #' . (int)$row['id_curso']),
                'nombre_modalidad'    => null,
                'pagado_en'           => $row['creado_en'],
                'pagado_en_formatted' => $fechaFmt,
                'moneda'              => null,
                'precio_unitario'     => null,
                'cantidad'            => 1,
                'inscripcion'         => [
                    'estado'    => 'Asignado',
                    'clase'     => 'bg-info text-dark',
                    'progreso'  => null,
                ],
                'origen'              => 'asignado',
            ];
        }
    }

    if (empty($asignadas) && !$isHrManager) {
        if ($userEmail === '' && $userId > 0) {
            try {
                $stmtEmailLookup = $pdo->prepare('SELECT email FROM usuarios WHERE id_usuario = ? LIMIT 1');
                $stmtEmailLookup->execute([$userId]);
                $fetchedEmail = $stmtEmailLookup->fetchColumn();
                if (is_string($fetchedEmail)) {
                    $userEmail = trim($fetchedEmail);
                }
            } catch (Throwable $emailLookupException) {
                error_log('mis_cursos email lookup: ' . $emailLookupException->getMessage());
            }
        }

        if ($checkoutCapacitacionesAvailable) {
            $capFallbackModalidadSelect = ($cursoModalidadAvailable && $modalidadesAvailable)
                ? 'mods.modalidad_resumen'
                : 'NULL AS modalidad_resumen';
            $capFallbackModalidadJoin = ($cursoModalidadAvailable && $modalidadesAvailable)
                ? <<<SQL
LEFT JOIN (
    SELECT cm.id_curso, GROUP_CONCAT(DISTINCT m.nombre_modalidad ORDER BY m.nombre_modalidad SEPARATOR ' / ') AS modalidad_resumen
    FROM curso_modalidad cm
    INNER JOIN modalidades m ON m.id_modalidad = cm.id_modalidad
    GROUP BY cm.id_curso
) AS mods ON mods.id_curso = cc.id_curso
SQL
                : '';

            $sqlCapFallback = <<<SQL
SELECT
    cc.id_capacitacion AS slot_id,
    cc.id_curso,
    cc.creado_en,
    cc.id_estado,
    c.nombre_curso,
    {$capFallbackModalidadSelect}
FROM checkout_capacitaciones cc
INNER JOIN cursos c ON c.id_curso = cc.id_curso
{$capFallbackModalidadJoin}
LEFT JOIN usuarios u ON u.email = cc.email
WHERE cc.email IS NOT NULL
  AND cc.email <> ''
  AND (
      u.id_usuario = :usuario
      OR (:email <> '' AND LOWER(cc.email) = LOWER(:email))
  )
  AND (cc.creado_por IS NULL OR cc.creado_por <> :usuario)
ORDER BY cc.creado_en DESC, cc.id_capacitacion DESC
SQL;
            $stmtCapFallback = $pdo->prepare($sqlCapFallback);
            $stmtCapFallback->bindValue(':usuario', $userId, PDO::PARAM_INT);
            $stmtCapFallback->bindValue(':email', $userEmail, PDO::PARAM_STR);
            $stmtCapFallback->execute();

            while ($row = $stmtCapFallback->fetch(PDO::FETCH_ASSOC)) {
                if (empty($row['id_curso']) || empty($row['slot_id'])) {
                    continue;
                }

                $fechaFmt = null;
                if (!empty($row['creado_en'])) {
                    try {
                        $fechaFmt = (new DateTimeImmutable((string)$row['creado_en']))->format('d/m/Y H:i');
                    } catch (Throwable $fallbackDateException) {
                        $fechaFmt = $row['creado_en'];
                    }
                }

                $asignadas[] = [
                    'id_item'             => -abs((int)$row['slot_id']),
                    'id_curso'            => (int)$row['id_curso'],
                    'tipo_curso'          => 'capacitacion',
                    'nombre_curso'        => $row['nombre_curso'] ?: ('Curso #' . (int)$row['id_curso']),
                    'nombre_modalidad'    => $row['modalidad_resumen'] ?? null,
                    'pagado_en'           => $row['creado_en'],
                    'pagado_en_formatted' => $fechaFmt,
                    'moneda'              => null,
                    'precio_unitario'     => null,
                    'cantidad'            => 1,
                    'inscripcion'         => [
                        'estado'    => 'Asignado',
                        'clase'     => 'bg-info text-dark',
                        'progreso'  => null,
                    ],
                    'origen'              => 'asignado',
                ];
            }
        }

        if ($checkoutCertificacionesAvailable) {
            $certFallbackModalidadSelect = ($cursoModalidadAvailable && $modalidadesAvailable)
                ? 'mods.modalidad_resumen'
                : 'NULL AS modalidad_resumen';
            $certFallbackModalidadJoin = ($cursoModalidadAvailable && $modalidadesAvailable)
                ? <<<SQL
LEFT JOIN (
    SELECT cm.id_curso, GROUP_CONCAT(DISTINCT m.nombre_modalidad ORDER BY m.nombre_modalidad SEPARATOR ' / ') AS modalidad_resumen
    FROM curso_modalidad cm
    INNER JOIN modalidades m ON m.id_modalidad = cm.id_modalidad
    GROUP BY cm.id_curso
) AS mods ON mods.id_curso = ccert.id_curso
SQL
                : '';

            $sqlCertFallback = <<<SQL
SELECT
    ccert.id_certificacion AS slot_id,
    ccert.id_curso,
    ccert.creado_en,
    ccert.id_estado,
    c.nombre_curso,
    {$certFallbackModalidadSelect}
FROM checkout_certificaciones ccert
INNER JOIN cursos c ON c.id_curso = ccert.id_curso
{$certFallbackModalidadJoin}
LEFT JOIN usuarios u ON u.email = ccert.email
WHERE ccert.email IS NOT NULL
  AND ccert.email <> ''
  AND (
      u.id_usuario = :usuario
      OR (:email <> '' AND LOWER(ccert.email) = LOWER(:email))
  )
  AND (ccert.creado_por IS NULL OR ccert.creado_por <> :usuario)
ORDER BY ccert.creado_en DESC, ccert.id_certificacion DESC
SQL;
            $stmtCertFallback = $pdo->prepare($sqlCertFallback);
            $stmtCertFallback->bindValue(':usuario', $userId, PDO::PARAM_INT);
            $stmtCertFallback->bindValue(':email', $userEmail, PDO::PARAM_STR);
            $stmtCertFallback->execute();

            while ($row = $stmtCertFallback->fetch(PDO::FETCH_ASSOC)) {
                if (empty($row['id_curso']) || empty($row['slot_id'])) {
                    continue;
                }

                $fechaFmt = null;
                if (!empty($row['creado_en'])) {
                    try {
                        $fechaFmt = (new DateTimeImmutable((string)$row['creado_en']))->format('d/m/Y H:i');
                    } catch (Throwable $fallbackCertDate) {
                        $fechaFmt = $row['creado_en'];
                    }
                }

                $asignadas[] = [
                    'id_item'             => -abs((int)$row['slot_id']),
                    'id_curso'            => (int)$row['id_curso'],
                    'tipo_curso'          => 'certificacion',
                    'nombre_curso'        => $row['nombre_curso'] ?: ('Curso #' . (int)$row['id_curso']),
                    'nombre_modalidad'    => null,
                    'pagado_en'           => $row['creado_en'],
                    'pagado_en_formatted' => $fechaFmt,
                    'moneda'              => null,
                    'precio_unitario'     => null,
                    'cantidad'            => 1,
                    'inscripcion'         => [
                        'estado'    => 'Asignado',
                        'clase'     => 'bg-info text-dark',
                        'progreso'  => null,
                    ],
                    'origen'              => 'asignado',
                ];
            }
        }
    }

    if (!empty($asignadas)) {
        $cursosComprados = array_merge($asignadas, $cursosComprados);
    }
} catch (Throwable $exception) {
    $errorDetails = $exception->getMessage();
    error_log('mis_cursos load: ' . $errorDetails);
    @file_put_contents(__DIR__ . '/mis_cursos_error.log', '[' . date('Y-m-d H:i:s') . '] ' . $errorDetails . PHP_EOL, FILE_APPEND);
    $errorMessage = 'No pudimos cargar tus cursos en este momento.';
}


$configActive = 'mis_cursos';

?>
<!DOCTYPE html>
<html lang="es">
<?php include 'head.php'; ?>
<body class="config-page d-flex flex-column min-vh-100">
<?php include 'nav.php'; ?>

<header class="config-hero">
    <div class="container">
        <a href="index.php" class="config-back"><i class="fas fa-arrow-left me-2"></i>Volver al inicio</a>
        <div class="row justify-content-center">
            <div class="col-xl-8">
                <div class="config-hero-card shadow-lg w-100 text-center">
                    <h1>Mis cursos</h1>
                </div>
            </div>
        </div>
    </div>
</header>

<main class="config-main flex-grow-1 py-5">
    <div class="container">
        <div class="row g-4 align-items-start position-relative">
            <!-- Sidebar dentro del flujo (mobile/tablet) -->
            <div class="col-12 d-xl-none mb-4">
                <?php include 'config_sidebar.php'; ?>
            </div>

            <!-- Contenido de cursos -->
            <div class="col-12 col-xl-10 mx-auto mis-cursos-content">
                <?php if ($misCursosFeedback !== null): ?>
                <?php $feedbackType = in_array($misCursosFeedback['type'] ?? '', $allowedFeedbackTypes, true) ? $misCursosFeedback['type'] : 'info'; ?>
                <div class="alert alert-<?php echo htmlspecialchars($feedbackType, ENT_QUOTES, 'UTF-8'); ?>" role="alert">
                    <?php echo htmlspecialchars($misCursosFeedback['message'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <?php endif; ?>

                <?php if ($errorMessage !== null): ?>
                    <div class="config-card shadow text-center">
                        <p class="mb-0"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                <?php elseif (empty($cursosComprados)): ?>
                    <div class="config-card shadow text-center">
                        <p class="mb-4">Todav&iacute;a no ten&eacute;s cursos adquiridos.</p>
                        <a class="btn btn-gradient" href="index.php#cursos">Explorar cursos disponibles</a>
                    </div>
                <?php else: ?>
    <?php
    // Separar en Capacitaciones vs Certificaciones (default: capacitaciones si no hay tipo)
    $capacitaciones = [];
    $certificaciones = [];
    foreach ($cursosComprados as $c) {
        $tipo = strtolower((string)($c['tipo_curso'] ?? ''));
        if ($tipo === 'certificacion' || $tipo === 'certificaciones') {
            $certificaciones[] = $c;
        } else {
            $capacitaciones[] = $c;
        }
    }
    $sections = [];
    if (!empty($capacitaciones)) { $sections['Capacitaciones'] = $capacitaciones; }
    if (!empty($certificaciones)) { $sections['Certificaciones'] = $certificaciones; }
    ?>

    <?php foreach ($sections as $sectionTitle => $lista): ?>
         <?php
            $sectionKey = strtolower($sectionTitle);
            $isCap = strpos($sectionKey, 'capacit') !== false;
            $sectionClass = $isCap ? 'section--cap' : 'section--cert';
            ?>
            <div class="section-header <?php echo $sectionClass; ?>">
            <h2><?php echo htmlspecialchars($sectionTitle, ENT_QUOTES, 'UTF-8'); ?></h2>
            </div>
        <div class="mis-cursos-grid row row-cols-1 row-cols-md-2 g-4 mb-5">
            <?php foreach ($lista as $curso): ?>
                <?php
                $inscripcion = $curso['inscripcion'];
                $precioUnitario = isset($curso['precio_unitario']) ? (float)$curso['precio_unitario'] : null;
                $moneda = (string)($curso['moneda'] ?? '');
                $precioLabel = null;
                if ($precioUnitario !== null) {
                    $formattedPrice = number_format($precioUnitario, 2, ',', '.');
                    $precioLabel = $moneda !== '' ? $moneda . ' ' . $formattedPrice : $formattedPrice;
                }
                ?>
                <div class="col-12 col-md-6">
                    <div class="config-card course-card shadow text-start h-100">
                        <div class="course-card__header">
                            <div class="course-card__headline d-flex align-items-start justify-content-between gap-3 flex-wrap">
                                <div class="course-card__info">

                                    <h2 class="h5 mb-1"><?php echo htmlspecialchars($curso['nombre_curso'] ?? 'Curso', ENT_QUOTES, 'UTF-8'); ?></h2>
                                    <?php if (!empty($curso['nombre_modalidad'])): ?>
                                        <div class="text-muted small">Modalidad: <?php echo htmlspecialchars($curso['nombre_modalidad'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="course-card__status text-md-end ms-md-auto">
                                    <?php if ($inscripcion !== null): ?>
                                        <span class="badge <?php echo htmlspecialchars($inscripcion['clase'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($inscripcion['estado'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php if ($inscripcion['progreso'] !== null): ?>
                                            <div class="text-muted small mt-2">Progreso: <?php echo (int)$inscripcion['progreso']; ?>%</div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="course-card__chips d-flex flex-wrap gap-2">
                                <span class="course-chip"> <?php if (($curso['origen'] ?? '') === 'asignado'): ?>
                                        Asignado el <?php echo htmlspecialchars($curso['pagado_en_formatted'] ?? 'Sin fecha', ENT_QUOTES, 'UTF-8'); ?>
                                    <?php else: ?>
                                        Comprado el <?php echo htmlspecialchars($curso['pagado_en_formatted'] ?? 'Sin fecha', ENT_QUOTES, 'UTF-8'); ?>
                                    <?php endif; ?>
                                </span>
                                <?php if ($precioLabel !== null): ?>
                                    <span class="course-chip course-chip--accent"><?php echo htmlspecialchars($precioLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endif; ?>
                                <?php if ($isHrManager): ?>
                                    <span class="course-chip">Total: <?php echo (int)$curso['cantidad']; ?></span>
                                    <span class="course-chip">Asignados: <?php echo isset($curso['asignados']) ? (int)$curso['asignados'] : 0; ?></span>
                                    <span class="course-chip">Disponibles: <?php echo isset($curso['disponibles']) ? (int)$curso['disponibles'] : max(0, (int)$curso['cantidad']); ?></span>
                                <?php elseif ($curso['cantidad'] > 1): ?>
                                    <span class="course-chip">Cantidad: <?php echo (int)$curso['cantidad']; ?></span>
                                <?php endif; ?>
                            </div>

                            <?php
                            $accionesMap = [];
                            if (!empty($curso['registros_checkout']) && is_array($curso['registros_checkout'])) {
                                foreach ($curso['registros_checkout'] as $registro) {
                                    $tipoRegistro = strtolower((string)($registro['tipo'] ?? ''));
                                    $registroId = (int)($registro['id_registro'] ?? 0);
                                    if ($registroId <= 0) {
                                        continue;
                                    }
                                    $estadoRegistro = (int)($registro['estado'] ?? 0);
                                    $pagoEstado = strtolower((string)($registro['pago_estado'] ?? ''));
                                    $pagoId = isset($registro['pago_id']) ? (int)$registro['pago_id'] : 0;

                                    if ($tipoRegistro === 'capacitacion') {
                                        if (in_array($estadoRegistro, [1, 2, 3], true)) {
                                            $ordenParam = $pagoId > 0 ? $pagoId : $registroId;
                                            $accionesMap['estado-cap-' . $registroId] = [
                                                'label' => 'Ver estado',
                                                'href' => 'checkout/gracias.php?' . http_build_query([
                                                    'tipo' => 'capacitacion',
                                                    'orden' => $ordenParam,
                                                ]),
                                                'variant' => 'outline-primary',
                                                'icon' => 'fas fa-info-circle',
                                            ];
                                        }

                                        if ($estadoRegistro !== 3) {
                                            if ($pagoEstado === 'pendiente') {
                                                $accionesMap['retomar-cap-' . $registroId] = [
                                                    'label' => 'Continuar pago',
                                                    'href' => 'checkout/retomar_pago.php?tipo=capacitacion&id=' . $registroId,
                                                    'variant' => 'primary',
                                                    'icon' => 'fas fa-credit-card',
                                                ];
                                            } elseif (in_array($pagoEstado, ['cancelado', 'rechazado'], true)) {
                                                $accionesMap['retomar-cap-' . $registroId] = [
                                                    'label' => 'Retomar pago',
                                                    'href' => 'checkout/retomar_pago.php?tipo=capacitacion&id=' . $registroId,
                                                    'variant' => 'primary',
                                                    'icon' => 'fas fa-rotate-right',
                                                ];
                                            }
                                        }
                                    } elseif ($tipoRegistro === 'certificacion') {
                                        $accionesMap['estado-cert-' . $registroId] = [
                                            'label' => 'Ver estado',
                                            'href' => 'checkout/gracias_certificacion.php?certificacion=' . $registroId,
                                            'variant' => 'outline-primary',
                                            'icon' => 'fas fa-info-circle',
                                        ];

                                        if (in_array($pagoEstado, ['cancelado', 'rechazado'], true) && $estadoRegistro === 2) {
                                            $accionesMap['retomar-cert-' . $registroId] = [
                                                'label' => 'Retomar pago',
                                                'href' => 'checkout/retomar_pago.php?tipo=certificacion&id=' . $registroId,
                                                'variant' => 'primary',
                                                'icon' => 'fas fa-rotate-right',
                                            ];
                                        }
                                    }
                                }
                            }

                            $acciones = array_values($accionesMap);
                            ?>
                            <?php if (!empty($acciones)): ?>
                                <div class="course-card__actions mt-3 d-flex flex-wrap gap-2">
                                    <?php foreach ($acciones as $accion): ?>
                                        <?php
                                        $variant = (string)($accion['variant'] ?? 'primary');
                                        $btnClass = $variant === 'outline-primary' ? 'btn-outline-primary' : 'btn-primary';
                                        $icon = (string)($accion['icon'] ?? '');
                                        ?>
                                        <a class="btn btn-sm <?php echo htmlspecialchars($btnClass, ENT_QUOTES, 'UTF-8'); ?>"
                                           href="<?php echo htmlspecialchars($accion['href'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php if ($icon !== ''): ?><i class="<?php echo htmlspecialchars($icon, ENT_QUOTES, 'UTF-8'); ?> me-1"></i><?php endif; ?>
                                            <?php echo htmlspecialchars($accion['label'], ENT_QUOTES, 'UTF-8'); ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($inscripcion !== null && $inscripcion['progreso'] !== null): ?>
                            <div class="course-card__progress progress mt-3">
                                <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo (int)$inscripcion['progreso']; ?>%;" aria-valuenow="<?php echo (int)$inscripcion['progreso']; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        <?php endif; ?>

                        <?php if ($isHrManager): ?>
                            <?php
                            $assignedWorkers = $curso['asignaciones'] ?? [];
                            $assignedWorkerIds = [];
                            foreach ($assignedWorkers as $assignmentData) {
                                $assignedWorkerIds[] = (int)($assignmentData['id_usuario'] ?? 0);
                            }
                            $availableWorkers = [];
                            foreach ($workersOptions as $workerOption) {
                                $workerOptionId = (int)($workerOption['id_usuario'] ?? 0);
                                if ($workerOptionId > 0 && !in_array($workerOptionId, $assignedWorkerIds, true)) {
                                    $availableWorkers[] = $workerOption;
                                }
                            }
                            $purchaseItems = $curso['purchase_items'] ?? [];
                            $hasPurchaseItems = !empty($purchaseItems);
                            $assignedCount = count($assignedWorkers);
                            $collapseSourceParts = [];
                            if (!empty($curso['tipo_curso'])) { $collapseSourceParts[] = (string)$curso['tipo_curso']; }
                            if (!empty($curso['id_curso'])) { $collapseSourceParts[] = (string)$curso['id_curso']; }
                            if (!empty($curso['id_item'])) { $collapseSourceParts[] = (string)$curso['id_item']; }
                            if (empty($collapseSourceParts)) { $collapseSourceParts[] = (string)($curso['nombre_curso'] ?? 'curso'); }
                            $assignedCollapseSource = strtolower(implode('-', $collapseSourceParts));
                            $assignedCollapseSlug = preg_replace('/[^a-z0-9_-]/', '-', $assignedCollapseSource);
                            if (!is_string($assignedCollapseSlug) || $assignedCollapseSlug === '') {
                                $assignedCollapseSlug = substr(md5($assignedCollapseSource !== '' ? $assignedCollapseSource : uniqid('assigned', true)), 0, 8);
                            }
                            $assignedCollapseSlug = trim((string)$assignedCollapseSlug, '-');
                            if ($assignedCollapseSlug === '') {
                                $assignedCollapseSlug = substr(md5(uniqid('assigned', true)), 0, 8);
                            }
                            $assignedCollapseId = 'assigned-workers-' . $assignedCollapseSlug;
                            ?>
                            <div class="course-card__section mt-4">
                                <div class="assigned-workers__section-header d-flex align-items-center justify-content-between gap-2">
                                    <h3 class="course-card__section-title h6 mb-0">Trabajadores asignados</h3>
                                    <?php if ($assignedCount > 0): ?>
                                        <button class="btn btn-sm btn-outline-secondary assigned-workers__toggle collapsed"
                                            type="button"
                                            data-bs-toggle="collapse"
                                            data-bs-target="#<?php echo htmlspecialchars($assignedCollapseId, ENT_QUOTES, 'UTF-8'); ?>"
                                            aria-expanded="false"
                                            aria-controls="<?php echo htmlspecialchars($assignedCollapseId, ENT_QUOTES, 'UTF-8'); ?>">
                                            Ver lista (<?php echo $assignedCount; ?>)
                                        </button>
                                    <?php endif; ?>
                                </div>
                                <?php if ($assignedCount === 0): ?>
                                    <p class="text-muted small mb-0">Todav&iacute;a no asignaste este curso.</p>
                                <?php else: ?>
                                    <div id="<?php echo htmlspecialchars($assignedCollapseId, ENT_QUOTES, 'UTF-8'); ?>" class="collapse assigned-workers__collapse">
                                        <div class="assigned-workers__scroll mt-3">
                                            <ul class="assigned-workers list-unstyled mb-0">
                                                <?php foreach ($assignedWorkers as $assignment): ?>
                                                    <?php
                                                    $fullName = trim((string)($assignment['nombre'] ?? '') . ' ' . (string)($assignment['apellido'] ?? ''));
                                                    if ($fullName === '') {
                                                        $fullName = (string)($assignment['email'] ?? 'Trabajador');
                                                    }
                                                    ?>
                                                    <li class="assigned-workers__item">
                                                        <div class="assigned-workers__header d-flex flex-wrap align-items-center justify-content-between gap-2">
                                                            <span class="fw-semibold"><?php echo htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'); ?></span>
                                                            <span class="badge <?php echo htmlspecialchars($assignment['clase'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($assignment['estado'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                        </div>
                                                        <?php if (!empty($assignment['email'])): ?>
                                                            <span class="text-muted small d-block"><?php echo htmlspecialchars((string)$assignment['email'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                        <?php endif; ?>
                                                    <?php if ($assignment['progreso'] !== null): ?>
                                                        <span class="text-muted small">Progreso: <?php echo (int)$assignment['progreso']; ?>%</span>
                                                    <?php endif; ?>
                                                    <?php if (($curso['tipo_curso'] ?? '') === 'certificacion'): ?>
                                                        <?php if (!empty($assignment['pdf_path'])): ?>
                                                            <?php
                                                            $pdfUrl = '/' . ltrim((string)$assignment['pdf_path'], '/');
                                                            $pdfLabel = trim((string)($assignment['pdf_nombre'] ?? 'Formulario enviado'));
                                                            if ($pdfLabel === '') {
                                                                $pdfLabel = 'Formulario enviado';
                                                            }
                                                            ?>
                                                            <div class="small mt-2">
                                                                <a class="link-primary" href="<?php echo htmlspecialchars($pdfUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">
                                                                    <i class="fas fa-file-pdf me-1"></i><?php echo htmlspecialchars($pdfLabel, ENT_QUOTES, 'UTF-8'); ?>
                                                                </a>
                                                            </div>
                                                        <?php endif; ?>
                                                        <?php if (!empty($assignment['puede_pagar']) && !empty($assignment['id_item_compra']) && !empty($curso['id_curso'])): ?>
                                                            <?php
                                                            $checkoutUrl = sprintf(
                                                                'checkout/checkout.php?id_certificacion=%d&tipo=certificacion&certificacion_registro=%d',
                                                                (int)$curso['id_curso'],
                                                                (int)$assignment['id_item_compra']
                                                            );
                                                            ?>
                                                            <a class="btn btn-sm btn-primary mt-2" href="<?php echo htmlspecialchars($checkoutUrl, ENT_QUOTES, 'UTF-8'); ?>">
                                                                <i class="fas fa-credit-card me-1"></i>Completar pago
                                                            </a>
                                                        <?php elseif (isset($assignment['estado_key']) && $assignment['estado_key'] === 'pagado'): ?>
                                                            <div class="small text-success mt-2">
                                                                <i class="fas fa-circle-check me-1"></i>Pago registrado
                                                            </div>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($hasPurchaseItems): ?>
                                <?php foreach ($purchaseItems as $purchaseItem): ?>
                                    <?php
                                    $purchaseItemToken = (string)($purchaseItem['id_item'] ?? '');
                                    if ($purchaseItemToken === '') { continue; }
                                    $purchaseItemSlug = preg_replace('/[^a-zA-Z0-9_-]/', '-', $purchaseItemToken);
                                    if ($purchaseItemSlug === '') { $purchaseItemSlug = 'item-' . substr(md5($purchaseItemToken), 0, 8); }
                                    $panelId = 'assign-panel-' . $purchaseItemSlug;
                                    $selectAllId = 'assign-select-all-' . $purchaseItemSlug;
                                    $formId = 'assign-form-' . $purchaseItemSlug;
                                    $availableSlots = isset($purchaseItem['disponibles']) ? (int)$purchaseItem['disponibles'] : max(0, (int)($purchaseItem['cantidad'] ?? 0));
                                    $maxSelectable = min($availableSlots, count($availableWorkers));
                                    $purchaseAssigned = $purchaseItem['asignaciones'] ?? [];
                                    $purchaseAssignedCount = isset($purchaseItem['asignados']) ? (int)$purchaseItem['asignados'] : count($purchaseAssigned);
                                    $purchaseDateLabel = $purchaseItem['pagado_en_formatted'] ?? ($purchaseItem['pagado_en'] ?? null);
                                    $canAssignPurchase = !empty($curso['can_assign']);
                                    ?>
                                    <div class="course-card__section mt-4">
                                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                                            <div>
                                                <h3 class="course-card__section-title h6 mb-1">Compra<?php echo $purchaseDateLabel ? ' (' . htmlspecialchars($purchaseDateLabel, ENT_QUOTES, 'UTF-8') . ')' : ' sin fecha'; ?></h3>
                                                <?php if (!empty($purchaseItem['nombre_modalidad'])): ?>
                                                    <p class="text-muted small mb-0"><?php echo htmlspecialchars($purchaseItem['nombre_modalidad'], ENT_QUOTES, 'UTF-8'); ?></p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="assign-panel__stats d-flex flex-wrap gap-3 align-items-center small text-muted">
                                                <span>Total: <strong><?php echo (int)($purchaseItem['cantidad'] ?? 0); ?></strong></span>
                                                <span>Asignados: <strong><?php echo $purchaseAssignedCount; ?></strong></span>
                                                <span>Disponibles: <strong data-remaining-count><?php echo $availableSlots; ?></strong></span>
                                            </div>
                                        </div>

                                        <?php if ($canAssignPurchase): ?>
                                            <button class="btn btn-outline-primary btn-sm<?php echo ($availableSlots <= 0 || empty($availableWorkers)) ? ' disabled' : ''; ?>" type="button" <?php if ($availableSlots > 0 && !empty($availableWorkers)): ?>data-bs-toggle="collapse" data-bs-target="#<?php echo htmlspecialchars($panelId, ENT_QUOTES, 'UTF-8'); ?>" aria-expanded="false" aria-controls="<?php echo htmlspecialchars($panelId, ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?>>
                                                Asignar trabajadores
                                            </button>
                                            <?php if ($availableSlots <= 0): ?>
                                                <p class="text-danger small mb-0 mt-2">No quedan cupos disponibles para esta compra.</p>
                                            <?php elseif (empty($workersOptions)): ?>
                                                <p class="text-muted small mb-0 mt-2">Todav&iacute;a no sumaste trabajadores a tu empresa.</p>
                                            <?php elseif (empty($availableWorkers)): ?>
                                                <p class="text-muted small mb-0 mt-2">Todos tus trabajadores ya tienen este curso asignado.</p>
                                            <?php endif; ?>

                                            <?php if ($availableSlots > 0 && !empty($availableWorkers)): ?>
                                                <div class="collapse mt-3" id="<?php echo htmlspecialchars($panelId, ENT_QUOTES, 'UTF-8'); ?>">
                                                    <div class="assign-panel shadow-sm">
                                                       <form method="POST"
                                                            enctype="multipart/form-data"
                                                            class="assign-workers-form"
                                                            id="<?php echo htmlspecialchars($formId, ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-available="<?php echo (int)$availableSlots; ?>"
                                                            data-is-cert="<?php echo (($curso['tipo_curso'] ?? '') === 'certificacion') ? '1' : '0'; ?>">
                                                            <input type="hidden" name="action" value="assign_workers">
                                                            <input type="hidden" name="item_id" value="<?php echo htmlspecialchars($purchaseItemToken, ENT_QUOTES, 'UTF-8'); ?>">
                                                            <div class="assign-panel__stats d-flex flex-wrap gap-3 align-items-center small text-muted mb-3">
                                                                <span>Cupos disponibles: <strong data-remaining-count><?php echo (int)$availableSlots; ?></strong></span>
                                                                <span>Seleccionados: <strong data-selected-count>0</strong></span>
                                                            </div>
                                                            <div class="form-check form-check-sm mb-2">
                                                                <input class="form-check-input assign-select-all" type="checkbox" id="<?php echo htmlspecialchars($selectAllId, ENT_QUOTES, 'UTF-8'); ?>" data-max-select="<?php echo (int)$maxSelectable; ?>">
                                                                <label class="form-check-label small" for="<?php echo htmlspecialchars($selectAllId, ENT_QUOTES, 'UTF-8'); ?>">
                                                                    Seleccionar todos (hasta <?php echo (int)$maxSelectable; ?>)
                                                                </label>
                                                            </div>
                                                            <?php if (($curso['tipo_curso'] ?? '') === 'certificacion'): ?>
                                                                <div class="mb-3">
                                                                    <label class="form-label small fw-semibold">Subir PDF (requisito/constancia)</label>
                                                                    <input type="file" name="cert_pdf" accept="application/pdf" class="form-control form-control-sm">
                                                                    <div class="form-text">Formato PDF, máx. 10MB. Se adjuntará al/los trabajadores seleccionados.</div>
                                                                </div>
                                                            <?php endif; ?>
                                                            <div class="assign-workers-list border rounded bg-white p-2" style="max-height: 220px; overflow: auto;">
                                                                <?php foreach ($availableWorkers as $worker): ?>
                                                                    <?php
                                                                    $workerId = (int)($worker['id_usuario'] ?? 0);
                                                                    if ($workerId <= 0) { continue; }
                                                                    $workerName = trim((string)($worker['nombre'] ?? '') . ' ' . (string)($worker['apellido'] ?? ''));
                                                                    if ($workerName === '') { $workerName = (string)($worker['email'] ?? 'Trabajador'); }
                                                                    $workerEmail = (string)($worker['email'] ?? '');
                                                                    $workerLabel = $workerName;
                                                                    if ($workerEmail !== '' && $workerName !== $workerEmail) {
                                                                        $workerLabel .= ' (' . $workerEmail . ')';
                                                                    }
                                                                    $inputId = 'assign-worker-' . $purchaseItemSlug . '-' . $workerId;
                                                                    ?>
                                                                   <div class="form-check form-check-sm mb-2">
                                                                        <input class="form-check-input assign-worker-checkbox"
                                                                            type="checkbox"
                                                                            name="worker_ids[]"
                                                                            value="<?php echo $workerId; ?>"
                                                                            id="<?php echo htmlspecialchars($inputId, ENT_QUOTES, 'UTF-8'); ?>"
                                                                            data-worker-id="<?php echo $workerId; ?>">
                                                                        <label class="form-check-label small" for="<?php echo htmlspecialchars($inputId, ENT_QUOTES, 'UTF-8'); ?>">
                                                                            <?php echo htmlspecialchars($workerLabel, ENT_QUOTES, 'UTF-8'); ?>
                                                                        </label>

                                                                        <?php if (($curso['tipo_curso'] ?? '') === 'certificacion'): ?>
                                                                            <?php $fileInputId = 'cert-pdf-' . $purchaseItemSlug . '-' . $workerId; ?>
                                                                            <input type="file"
                                                                                name="cert_pdf[<?php echo $workerId; ?>]"
                                                                                id="<?php echo htmlspecialchars($fileInputId, ENT_QUOTES, 'UTF-8'); ?>"
                                                                                accept="application/pdf"
                                                                                class="form-control form-control-sm mt-1 d-none"
                                                                                data-file-for-worker="<?php echo $workerId; ?>">
                                                                            <div class="form-text d-none" data-file-hint-for="<?php echo $workerId; ?>">
                                                                                PDF requerido para este trabajador (máx. 10MB).
                                                                            </div>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                            <p class="text-muted small mt-3 mb-3">Vas a crear una inscripci&oacute;n por cada trabajador seleccionado. Esta acci&oacute;n no se puede revertir.</p>
                                                            <div class="d-flex flex-wrap gap-2">
                                                                <button type="submit" class="btn btn-primary btn-sm" data-assign-submit disabled>
                                                                    Asignar
                                                                </button>
                                                                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="collapse" data-bs-target="#<?php echo htmlspecialchars($panelId, ENT_QUOTES, 'UTF-8'); ?>">
                                                                    Cancelar
                                                                </button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <p class="text-muted small mb-0 mt-2">Las asignaciones no est&aacute;n disponibles en este entorno.</p>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="course-card__section mt-4">
                                    <p class="text-muted small mb-0">Todav&iacute;a no ten&eacute;s compras con cupos disponibles para asignar.</p>
                                </div>
                            <?php endif; ?>

                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

            </div>



            </div>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var assignForms = document.querySelectorAll('.assign-workers-form');

    assignForms.forEach(function (form) {
        var available = parseInt(form.getAttribute('data-available'), 10);
        if (isNaN(available) || available < 0) {
            available = 0;
        }

        var checkboxes = Array.prototype.slice.call(form.querySelectorAll('.assign-worker-checkbox'));
        var selectAll = form.querySelector('.assign-select-all');
        var selectedCountNode = form.querySelector('[data-selected-count]');
        var remainingCountNode = form.querySelector('[data-remaining-count]');
        var submitButton = form.querySelector('[data-assign-submit]');

        var updateState = function () {
            var selected = checkboxes.filter(function (checkbox) {
                return checkbox.checked;
            }).length;

            if (selectedCountNode) {
                selectedCountNode.textContent = selected;
            }

            var remaining = Math.max(available - selected, 0);
            if (remainingCountNode) {
                remainingCountNode.textContent = remaining;
            }

            if (submitButton) {
                submitButton.disabled = selected === 0 || selected > available;
            }

            var limit = Math.min(available, checkboxes.length);
            if (selectAll) {
                if (limit <= 0) {
                    selectAll.checked = false;
                    selectAll.indeterminate = false;
                    selectAll.disabled = true;
                } else {
                    var allSelected = selected >= limit;
                    selectAll.checked = allSelected;
                    selectAll.indeterminate = selected > 0 && !allSelected;
                    selectAll.disabled = false;
                }
            }

            if (available > 0) {
                var shouldDisable = selected >= available;
                checkboxes.forEach(function (checkbox) {
                    if (!checkbox.checked) {
                        checkbox.disabled = shouldDisable;
                    } else {
                        checkbox.disabled = false;
                    }
                });
            }
        };

        checkboxes.forEach(function (checkbox) {
            checkbox.addEventListener('change', function () {
                if (checkbox.checked) {
                    var selected = checkboxes.filter(function (cb) {
                        return cb.checked;
                    }).length;
                    if (selected > available) {
                        checkbox.checked = false;
                        return;
                    }
                }
                updateState();
            });
        });

        if (selectAll) {
            selectAll.addEventListener('change', function () {
                if (!selectAll.checked) {
                    checkboxes.forEach(function (checkbox) {
                        checkbox.checked = false;
                        checkbox.disabled = false;
                    });
                    updateState();
                    return;
                }

                var allowed = Math.min(available, checkboxes.length);
                var selected = 0;

                checkboxes.forEach(function (checkbox) {
                    if (selected < allowed) {
                        if (!checkbox.checked) {
                            checkbox.checked = true;
                        }
                        selected += 1;
                    } else {
                        checkbox.checked = false;
                    }
                });

                updateState();
            });
        }

        form.addEventListener('submit', function (event) {
            var selected = checkboxes.filter(function (checkbox) {
                return checkbox.checked;
            }).length;

            if (selected === 0) {
                event.preventDefault();
                window.alert('Selecciona al menos un trabajador para continuar.');
                return;
            }

            if (selected > available) {
                event.preventDefault();
                window.alert('Seleccionaste mas trabajadores que cupos disponibles.');
                return;
            }

            var message = selected === 1
                ? 'Se asignara 1 trabajador al curso. Esta accion no se puede revertir. Deseas continuar?'
                : 'Se asignaran ' + selected + ' trabajadores al curso. Esta accion no se puede revertir. Deseas continuar?';

            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });

        updateState();
    });
});
</script>
<?php if ($misCursosAlert !== null): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var data = <?php echo json_encode($misCursosAlert, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;

            if (!data) {
                return;
            }

            var title = data.title || 'Sesion iniciada';
            var message = data.message || '';
            var text = (message && message.trim()) ? message : title;

            var styleId = 'mis-cursos-toast-style';
            if (!document.getElementById(styleId)) {
                var style = document.createElement('style');
                style.id = styleId;
                style.textContent = 'n                    .floating-login-alert {n                        position: fixed;n                        top: 1rem;n                        right: 1rem;n                        z-index: 2000;n                        min-width: 220px;n                        max-width: 320px;n                        padding: 0.75rem 1rem;n                        border-radius: 0.5rem;n                        background-color: #198754;n                        border: 1px solid #146c43;n                        color: #fff;n                        box-shadow: 0 0.5rem 1rem rgba(25, 135, 84, 0.35);n                        opacity: 0;n                        transform: translateY(-10px);n                        transition: opacity 200ms ease, transform 200ms ease;n                    }n                    .floating-login-alert.show {n                        opacity: 1;n                        transform: translateY(0);n                    }n                    .floating-login-alert.hide {n                        opacity: 0;n                        transform: translateY(-10px);n                    }n                ';
                document.head.appendChild(style);
            }

            var alertNode = document.createElement('div');
            alertNode.className = 'floating-login-alert alert alert-success';
            alertNode.setAttribute('role', 'alert');
            alertNode.textContent = text;

            var offsetTop = 16;
            var stickyNav = document.querySelector('.navbar.sticky-top');
            if (stickyNav) {
                offsetTop = stickyNav.getBoundingClientRect().height + 16;
            }
            alertNode.style.top = offsetTop + 'px';

            alertNode.addEventListener('click', function () {
                alertNode.classList.add('hide');
            });

            alertNode.addEventListener('transitionend', function (event) {
                if (event.propertyName === 'opacity' && alertNode.classList.contains('hide')) {
                    alertNode.remove();
                }
            });

            document.body.appendChild(alertNode);

            requestAnimationFrame(function () {
                alertNode.classList.add('show');
            });

            setTimeout(function () {
                alertNode.classList.add('hide');
            }, 5000);
        });
    </script>
<?php endif; ?>
</body>
</html>
