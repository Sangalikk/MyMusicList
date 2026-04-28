<?php
require_once('carregarTwig.php');

// Configurações do Spotify (Pegue em developer.spotify.com)
$clientId = 'bda7aef332c14b439c1836e5b1f2932b';
$clientSecret = 'd89f9e23499b451b973b883365706e1b';

function getSpotifyAccessToken($id, $secret) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://accounts.spotify.com/api/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Basic ' . base64_encode($id . ':' . $secret),
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    $result = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($result, true);
    return $data['access_token'] ?? null;
}

function searchSpotify($query, $token) {
    if (empty($query)) return [];
    
    $params = [
        'q' => $query,
        'type' => 'track',
        'limit' => 20
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.spotify.com/v1/search?' . http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token]);
    $result = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($result, true);
    return $data['tracks']['items'] ?? [];
}

$query = $_GET['q'] ?? '';
$results = [];

if ($query) {
    $token = getSpotifyAccessToken($clientId, $clientSecret);
    if ($token) {
        $results = searchSpotify($query, $token);
    }
}

// Mapeamos os dados da API para o formato que seu banco/template espera
$tracks = array_map(function($item) {
    return [
        'external_id' => $item['id'],
        'title' => $item['name'],
        'artist_name' => $item['artists'][0]['name'],
        'album_name' => $item['album']['name'],
        'image_url' => $item['album']['images'][0]['url'] ?? '',
        'duration_seconds' => floor($item['duration_ms'] / 1000)
    ];
}, $results);

echo $twig->render('pesquisa.html', [
    'query' => $query,
    'tracks' => $tracks
]);