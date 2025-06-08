<?php
// events/actions/index.php - Doğrudan erişimi engelle

http_response_code(403);
exit('Direct access not allowed');
?>