<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Ai\AiService;
use App\Services\Ai\EmbeddingService;

echo "profil aktif: " . config('ai.active_profile') . "\n";
echo "embed provider: " . config('ai.embedding.provider') . " model=" . config('ai.embedding.model') . " dims=" . config('ai.embedding.dimensions') . "\n";

$ai = app(AiService::class);
$out = $ai->run('generate', 'Anda asisten. Balas HANYA JSON.', 'Sebutkan 2 warna dalam format {"warna":["...","..."]}.', ['institusi_id' => null, 'mode' => 'smoke']);
echo "GENERATE provider=" . ($out->interaksi->provider ?? '?') . " model=" . ($out->interaksi->model ?? '?') . "\n";
echo "TEXT: " . substr($out->text(), 0, 160) . "\n";

$emb = app(EmbeddingService::class);
$r = $emb->embed('kurikulum berbasis capaian pembelajaran OBE', ['institusi_id' => null]);
echo "EMBED provider={$r['provider']} model={$r['model']} dims=" . count($r['embedding']) . " mock=" . ($r['mock'] ? 'ya' : 'tidak') . "\n";
