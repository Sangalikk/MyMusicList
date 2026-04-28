<?php
if (extension_loaded('curl')) {
    echo "✅ O cURL está ATIVADO no seu servidor.";
} else {
    echo "❌ O cURL está DESATIVADO.";
}

echo "<br><br>--- Detalhes do PHP ---<br>";
phpinfo();
?>