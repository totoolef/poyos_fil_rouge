<?php
include('jwt_utils.php');

function authorizeRequest($expectedRole = null, $expectedUserId = null) {
    $token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $token = str_replace('Bearer ', '', $token);
    $decoded = validateJWT($token);

    if (!$decoded) {
        return ['success' => false, 'message' => 'Token invalide'];
    }

    $role = strtolower($decoded->role ?? '');
    $userId = $decoded->sub ?? null;

    if ($expectedRole && $role !== strtolower($expectedRole)) {
        return ['success' => false, 'message' => 'Rôle non autorisé'];
    }

    if ($expectedUserId && $userId != $expectedUserId) {
        return ['success' => false, 'message' => 'ID utilisateur non correspondant'];
    }

    return ['success' => true, 'userId' => $userId, 'role' => $role, 'decoded' => $decoded];
}
