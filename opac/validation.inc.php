<?php
/*
 * File: validation.inc.php
 * Created on Thu Oct 01 2026
 * Last Updated: Thu Oct 01 2026 5:53:14 PM
 * Author: Erwan Setyo Budi
 * Email: erwans818@gmail.com
 * License: The GNU General Public License, Version 3 (GPL-3.0) - Copyright (C) 2026 Erwan Setyo Budi. This program is free software.
 */

require_once __DIR__.'/../helper.php';
$code=trim($_GET['code']??''); $doc=null;
if($code!==''){ $st=tte_db()->prepare('SELECT verification_id,document_title,document_number,description,signer_name,signed_at,document_hash,status FROM tte_documents WHERE verification_id=? LIMIT 1'); $st->execute([$code]); $doc=$st->fetch(PDO::FETCH_ASSOC)?:null; }
?>
<div class="container py-5" style="max-width:850px">
 <h2>Validasi Dokumen</h2>
 <form method="get" action="index.php" class="mb-4"><input type="hidden" name="p" value="tte-validation"><div class="input-group"><input name="code" value="<?=tte_h($code)?>" class="form-control" placeholder="Masukkan ID, contoh TTE-20260928-AB12CD34" required><button class="btn btn-primary">Validasi</button></div></form>
 <?php if($code==='' ): ?><div class="alert alert-info">Masukkan ID verifikasi atau pindai QR pada dokumen.</div>
 <?php elseif(!$doc): ?><div class="alert alert-danger"><h4>DOKUMEN TIDAK DITEMUKAN</h4>ID verifikasi tidak terdaftar.</div>
 <?php else: $ok=$doc['status']==='signed'; ?><div class="card"><div class="card-body"><div class="alert <?=$ok?'alert-success':'alert-warning'?>"><h3><?=$ok?'✓ DOKUMEN TERVERIFIKASI':'⚠ PENGESAHAN DICABUT'?></h3></div><table class="table"><tr><th>ID</th><td><?=tte_h($doc['verification_id'])?></td></tr><tr><th>Judul</th><td><?=tte_h($doc['document_title'])?></td></tr><tr><th>Nomor</th><td><?=tte_h($doc['document_number'])?></td></tr><tr><th>Deskripsi</th><td><?=nl2br(tte_h($doc['description']))?></td></tr><tr><th>Ditandatangani oleh</th><td><?=tte_h($doc['signer_name'])?></td></tr><tr><th>Waktu</th><td><?=tte_h($doc['signed_at'])?> WIB</td></tr><tr><th>SHA-256</th><td style="word-break:break-all"><code><?=tte_h($doc['document_hash'])?></code></td></tr></table><p class="text-muted">Halaman ini memverifikasi catatan pengesahan internal yang tersimpan di SLiMS. Hash SHA-256 dapat digunakan untuk membandingkan integritas file PDF.</p></div></div><?php endif; ?>
</div>
