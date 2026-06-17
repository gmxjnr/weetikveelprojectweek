<?php

require_once "db.php";
require_once "modules/Files.php";

$files = new Files($pdo);

$token = $_GET['token'] ?? null;

if (!$token) {
    die("Geen token opgegeven");
}

$file = $files->getByToken($token);

if (!$file) {
    die("Bestand niet gevonden");
}

$path = __DIR__ . "/modules/data/" . $file['directory'] . "/" . $file['stored_filename'];

if (!file_exists($path)) {
    die("Bestand bestaat niet meer");
}

// raw=1 -> hand back the encrypted bytes so the browser's JS can decrypt them.
// The server only ever sends ciphertext; it cannot read the file itself.
if (isset($_GET['raw'])) {
    header('Content-Type: application/octet-stream');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Download File</title>
</head>
<body>

<h2>Bestand ontsleutelen</h2>
<p id="status">Bezig met ophalen en ontsleutelen...</p>
<div id="result" hidden></div>

<noscript>Deze pagina heeft JavaScript nodig voor de ontsleuteling.</noscript>

<script>
// ---- helper: base64url -> bytes ----
function base64urlToBytes(str) {
    str = str.replace(/-/g, '+').replace(/_/g, '/');
    const bin = atob(str);
    const out = new Uint8Array(bin.length);
    for (let i = 0; i < bin.length; i++) out[i] = bin.charCodeAt(i);
    return out;
}

const statusEl = document.getElementById('status');
const resultEl = document.getElementById('result');

(async () => {
    const params = new URLSearchParams(location.search);
    const token = params.get('token');

    // The key lives in the #fragment, which the server never received.
    const hash = new URLSearchParams(location.hash.slice(1));
    const keyB64 = hash.get('k');

    if (!keyB64) {
        statusEl.textContent = 'Geen sleutel in de link — ontsleutelen onmogelijk.';
        return;
    }

    try {
        // 1. Fetch the encrypted bytes from the server.
        const res = await fetch('download.php?raw=1&token=' + encodeURIComponent(token));
        const blob = new Uint8Array(await res.arrayBuffer());

        // 2. Split off the IV we prepended during upload (first 12 bytes).
        const iv = blob.slice(0, 12);
        const ciphertext = blob.slice(12);

        // 3. Rebuild the key from the link and decrypt (AES-GCM also verifies integrity).
        const key = await crypto.subtle.importKey(
            'raw', base64urlToBytes(keyB64), { name: 'AES-GCM' }, false, ['decrypt']
        );
        const plaintext = new Uint8Array(
            await crypto.subtle.decrypt({ name: 'AES-GCM', iv }, key, ciphertext)
        );

        // 4. Read back the [nameLen][name][content] layout we built when encrypting.
        const nameLen = new DataView(plaintext.buffer).getUint16(0);
        const name = new TextDecoder().decode(plaintext.slice(2, 2 + nameLen));
        const content = plaintext.slice(2 + nameLen);

        // 5. Offer the decrypted file as a normal download.
        const url = URL.createObjectURL(new Blob([content]));
        const a = document.createElement('a');
        a.href = url;
        a.download = name;
        a.textContent = 'Download ' + name;

        statusEl.textContent = 'Ontsleuteld!';
        resultEl.appendChild(a);
        resultEl.hidden = false;
    } catch (err) {
        statusEl.textContent = 'Kon het bestand niet ontsleutelen (verkeerde of beschadigde link).';
    }
})();
</script>

</body>
</html>
