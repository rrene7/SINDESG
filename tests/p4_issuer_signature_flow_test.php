<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Services/P4IssuerSignature.php';
require dirname(__DIR__).'/app/Repositories/DeliveryRepository.php';
use App\Services\P4IssuerSignature;
use App\Repositories\DeliveryRepository;

foreach (['','data:text/plain;base64,QQ==','data:image/png;base64,not-base64'] as $bad) {
    try { P4IssuerSignature::decodePng($bad); throw new LogicException('Accepted malformed signature'); }
    catch (InvalidArgumentException $expected) {}
}
// Exercise a real PNG payload, not only static source wiring.
$chunk=static function(string $type,string $data):string {
    return pack('N',strlen($data)).$type.$data.pack('N',crc32($type.$data));
};
$png="\x89PNG\r\n\x1a\n";
$png.=$chunk('IHDR',pack('NNCCCCC',100,60,8,6,0,0,0));
$png.=$chunk('IDAT',gzcompress(str_repeat("\x00".str_repeat("\xff",400),60)));
$png.=$chunk('IEND','');
if (P4IssuerSignature::decodePng('data:image/png;base64,'.base64_encode($png))!==$png) {
    throw new RuntimeException('Valid issuer signature PNG rejected.');
}
try {
    P4IssuerSignature::validatePng("NOTPNG".$png);
    throw new LogicException('Disguised image accepted');
} catch (InvalidArgumentException $expected) {}

if (!in_array('sqlite',PDO::getAvailableDrivers(),true)) throw new RuntimeException('sqlite required');
$pdo=new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE individual_deliveries(id INTEGER PRIMARY KEY,status TEXT);
CREATE TABLE individual_delivery_issuer_signatures(delivery_id INTEGER PRIMARY KEY,issuer_user_id INTEGER,signature_png BLOB,signature_sha256 TEXT,captured_at TEXT);
INSERT INTO individual_deliveries VALUES(1,'confirmed'),(2,'draft');
INSERT INTO individual_delivery_issuer_signatures VALUES(1,31,x'89504E470D0A1A0A','h1','2026-10-19'),(2,32,x'89504E470D0A1A0A','h2','2026-10-19');");
$repo=new DeliveryRepository($pdo);
$proof=$repo->issuerReceiptEvidence(1);
if (!$proof || (int)$proof['issuer_user_id']!==31 || $repo->issuerReceiptEvidence(2)!==null) {
    throw new RuntimeException('Historic issuer evidence read is not status-guarded.');
}
$root=dirname(__DIR__);
$ctrl=(string)file_get_contents($root.'/app/Controllers/DeliveryController.php');
$deliveryRepo=(string)file_get_contents($root.'/app/Repositories/DeliveryRepository.php');
$view=(string)file_get_contents($root.'/app/Views/deliveries/show.php');
$profile=(string)file_get_contents($root.'/app/Controllers/ProfileController.php');
$router=(string)file_get_contents($root.'/public/index.php');
$security=(string)file_get_contents($root.'/app/bootstrap.php');
$auth=(string)file_get_contents($root.'/app/Services/AuthService.php');
$schema=(string)file_get_contents($root.'/database/schema/022_p4_issuer_signatures.sql');
foreach ([
 [$ctrl, "issuer_ack"],[$ctrl,"'issuer_user_id' => \$actorId"],
 [$deliveryRepo, "FOR UPDATE"],[$deliveryRepo, "INSERT INTO individual_delivery_issuer_signatures"],
 [$view,"!empty(\$issuerReceiptEvidence)"],[$view,'name="issuer_ack"'],
 [$profile,"password_verify("],[$profile,"P4IssuerSignature::decodePng"],
 [$profile,"is_uploaded_file("],[$router,"ProfileController"],
 [$security,"if (\$module === 'profile')"],[$auth,"'signature_png'"],
 [$auth,"'recipient_signature_png'"],[$schema,"individual_delivery_issuer_signatures"],
 [$schema,"p4_user_signature_history"]
] as [$source,$fragment]) {
    if(!str_contains($source,$fragment))throw new RuntimeException("Signature integration missing: ".$fragment);
}
if(!str_contains($view,'Sin firma personal archivada en esta entrega histórica')) {
    throw new RuntimeException('Historic old receipt fallback missing.');
}
echo "[OK] P4 issuer signature, consent, historical snapshots and signed receipt wiring.\n";
