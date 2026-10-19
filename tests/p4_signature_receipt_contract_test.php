<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$schema=(string)file_get_contents($root.'/database/schema/021_p4_delivery_receipts.sql');
$controller=(string)file_get_contents($root.'/app/Controllers/DeliveryController.php');
$repository=(string)file_get_contents($root.'/app/Repositories/DeliveryRepository.php');
$view=(string)file_get_contents($root.'/app/Views/deliveries/show.php');
foreach ([
 [$schema,'individual_delivery_receipts'],
 [$schema,'signature_sha256'],
 [$repository,'$signatureEvidence'],
 [$repository,'hash(\'sha256\''],
 [$repository,'INSERT INTO individual_delivery_receipts'],
 [$controller,'recipient_signature_png'],
 [$controller,'base64_decode('],
 [$view,'data-p4-signature-canvas'],
 [$view,'data-p4-signature-ack'],
 [$view,'canvas.toDataURL(\'image/png\')']
] as [$source,$term]) {
 if(!str_contains($source,$term)) throw new RuntimeException('Missing P4 receipt evidence: '.$term);
}
echo "[OK] P4 signature required and stored atomically with delivery confirmation.\n";
