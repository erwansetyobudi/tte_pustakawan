<?php
/*
 * File: index.inc.php
 * Created on Thu Oct 01 2026
 * Last Updated: Thu Oct 01 2026 5:53:37 PM
 * Author: Erwan Setyo Budi
 * Email: erwans818@gmail.com
 * License: The GNU General Public License, Version 3 (GPL-3.0) - Copyright (C) 2026 Erwan Setyo Budi. This program is free software.
 */

defined('INDEX_AUTH') OR die('Direct access not allowed!');
require_once LIB.'ip_based_access.inc.php'; do_checkIP('smc');
require_once SB.'admin/default/session.inc.php';
require_once __DIR__.'/../helper.php';
if(!utility::havePrivilege('system','r')) die('<div class="alert alert-danger">Tidak memiliki hak akses.</div>');
$db=tte_db(); $canWrite=utility::havePrivilege('system','w');

if(isset($_GET['file'],$_GET['doc'])){ $d=tte_get_doc((int)$_GET['doc']); tte_stream_file($d,$_GET['file']==='original'?'original':'signed',isset($_GET['inline'])); }

$view=$_GET['view']??'list';
$msg='';
if(isset($_GET['tte_msg']) && $_GET['tte_msg']==='signed') $msg='Dokumen berhasil ditandatangani.';
if(isset($_GET['tte_error']) && $_GET['tte_error']!=='') $msg='ERROR: '.trim((string)$_GET['tte_error']);
if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['tte_action']??'')==='upload' && $canWrite){
    try{
        if(empty($_FILES['pdf']['tmp_name']) || $_FILES['pdf']['error']!==UPLOAD_ERR_OK) throw new RuntimeException('File PDF wajib dipilih.');
        if(strtolower(pathinfo($_FILES['pdf']['name'],PATHINFO_EXTENSION))!=='pdf') throw new RuntimeException('File harus berformat PDF.');
        $fh=fopen($_FILES['pdf']['tmp_name'],'rb'); $magic=fread($fh,5); fclose($fh);
        if($magic!=='%PDF-') throw new RuntimeException('File yang diunggah bukan PDF valid.');
        $code=tte_random_id(); $filename=$code.'-original.pdf';
        if(!move_uploaded_file($_FILES['pdf']['tmp_name'],tte_storage('original').'/'.$filename)) throw new RuntimeException('Gagal menyimpan PDF.');
        $now=date('Y-m-d H:i:s');
        $st=$db->prepare('INSERT INTO tte_documents (verification_id,document_title,document_number,description,original_file,signer_uid,signer_name,status,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?)');
        $st->execute([$code,trim($_POST['document_title']),trim($_POST['document_number']??''),trim($_POST['description']??''),$filename,(int)$_SESSION['uid'],$_SESSION['realname'],'draft',$now,$now]);
        $newId=(int)$db->lastInsertId();
        if(isset($_GET['upload_frame'])){
            echo '<script>parent.jQuery("#mainContent").simbioAJAX('.json_encode(tte_admin_url(['view'=>'place','doc'=>$newId])).');</script>';
            exit;
        }
        $view='place'; $_GET['doc']=$newId;
    }catch(Throwable $e){ $msg=$e->getMessage(); }
}

if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['tte_action']??'')==='sign' && $canWrite){
    try{
        $doc=tte_get_doc((int)$_POST['doc']); if(!$doc) throw new RuntimeException('Dokumen tidak ditemukan.');
        if((int)$doc['signer_uid']!==(int)$_SESSION['uid']) throw new RuntimeException('Dokumen ini hanya dapat ditandatangani oleh akun yang mengunggahnya.');
        if($doc['status']!=='draft') throw new RuntimeException('Dokumen sudah diproses.');
        $res=tte_sign_pdf($doc,(int)$_POST['page_number'],(float)$_POST['position_x'],(float)$_POST['position_y'],(float)$_POST['qr_size'],
            !empty($_POST['show_signer_text']),!empty($_POST['show_datetime']),!empty($_POST['show_tte_id']));
        $st=$db->prepare("UPDATE tte_documents SET signed_file=?,signed_at=?,page_number=?,position_x=?,position_y=?,qr_size=?,document_hash=?,status='signed',updated_at=? WHERE id=?");
        $st->execute([$res['file'],$res['signed_at'],$res['page'],(float)$_POST['position_x'],(float)$_POST['position_y'],(float)$_POST['qr_size'],$res['hash'],date('Y-m-d H:i:s'),(int)$doc['id']]);
        if(function_exists('writeLog')) writeLog('staff',$_SESSION['uid'],'system','Menandatangani dokumen '.$doc['verification_id'],'TTE','Sign');
        if(isset($_GET['sign_frame'])){
            $target=tte_admin_url(['view'=>'detail','doc'=>$doc['id'],'tte_msg'=>'signed']);
            echo '<script>parent.parent.jQuery("#mainContent").simbioAJAX('.json_encode($target).');</script>';
            exit;
        }
        $view='detail'; $_GET['doc']=$doc['id']; $msg='Dokumen berhasil ditandatangani.';
    }catch(Throwable $e){
        $docId=(int)($_POST['doc']??0);
        if(isset($_GET['sign_frame'])){
            $target=tte_admin_url(['view'=>'place','doc'=>$docId,'tte_error'=>$e->getMessage()]);
            echo '<script>parent.parent.jQuery("#mainContent").simbioAJAX('.json_encode($target).');</script>';
            exit;
        }
        $view='place'; $_GET['doc']=$docId; $msg=$e->getMessage();
    }
}


if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['tte_action']??'')==='update' && $canWrite){
    $doc=tte_get_doc((int)($_POST['doc']??0));
    if($doc){
        $st=$db->prepare('UPDATE tte_documents SET document_title=?,document_number=?,description=?,updated_at=? WHERE id=?');
        $st->execute([trim($_POST['document_title']??''),trim($_POST['document_number']??''),trim($_POST['description']??''),date('Y-m-d H:i:s'),$doc['id']]);
        $msg='Data dokumen berhasil diperbarui.'; $view='list';
    }
}

if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['tte_action']??'')==='delete' && $canWrite){
    $doc=tte_get_doc((int)($_POST['doc']??0));
    if($doc){
        foreach(['original_file'=>'original','signed_file'=>'signed'] as $field=>$dir){
            if(!empty($doc[$field])){
                $f=tte_storage($dir).'/'.basename($doc[$field]);
                if(is_file($f)) @unlink($f);
            }
        }
        $db->prepare('DELETE FROM tte_documents WHERE id=?')->execute([$doc['id']]);
        if(function_exists('writeLog')) writeLog('staff',$_SESSION['uid'],'system','Menghapus dokumen TTE '.$doc['verification_id'],'TTE','Delete');
        $msg='Data dokumen berhasil dihapus.'; $view='list';
    }
}

if(isset($_POST['tte_action']) && $_POST['tte_action']==='revoke' && $canWrite){
    $doc=tte_get_doc((int)$_POST['doc']);
    if($doc){ $db->prepare("UPDATE tte_documents SET status='revoked',updated_at=? WHERE id=?")->execute([date('Y-m-d H:i:s'),$doc['id']]); $msg='Pengesahan dokumen dicabut.'; $view='detail'; $_GET['doc']=$doc['id']; }
}
?>
<div class="menuBox">
  <div class="menuBoxInner systemIcon">
    <div class="per_title"><h2>Tanda Tangan Elektronik Pustakawan</h2></div>
    <div class="infoBox">Pengesahan internal PDF dengan QR verifikasi, identitas akun SLiMS, waktu tanda tangan dan SHA-256.</div>
  </div>
</div>
<?php if($msg): ?><div class="alert alert-info"><?=tte_h($msg)?></div><?php endif; ?>
<?php if($view==='add'): ?>
<div class="sub_section"><h3>Unggah Dokumen</h3>
<form id="tteUpload" class="notAJAX" method="post" enctype="multipart/form-data" action="<?=tte_h(tte_admin_url(['upload_frame'=>1]))?>" target="tteUploadFrame">
<input type="hidden" name="mod" value="<?=tte_h(tte_plugin_mod())?>"><input type="hidden" name="id" value="<?=tte_h(tte_plugin_id())?>"><input type="hidden" name="tte_action" value="upload">
<div class="form-group"><label>Penandatangan</label><input class="form-control" value="<?=tte_h($_SESSION['realname'])?>" readonly></div>
<div class="form-group"><label>Judul Dokumen *</label><input name="document_title" class="form-control" required></div>
<div class="form-group">
<label>Nomor Dokumen</label>
<div class="input-group">
 <input name="document_number" id="tteDocumentNumber" class="form-control">
 <span class="input-group-btn"><button type="button" class="btn btn-default" id="tteGenerateNumber" title="Buat nomor dokumen otomatis">Generate Nomor</button></span>
</div>
<small>Nomor tetap dapat diubah secara manual setelah dibuat.</small>
</div>
<script>
(function($){
 $('#tteGenerateNumber').off('click.tte').on('click.tte',function(){
   var d=new Date(), pad=function(n){return String(n).padStart(2,'0');};
   var stamp=d.getFullYear()+pad(d.getMonth()+1)+pad(d.getDate())+'-'+pad(d.getHours())+pad(d.getMinutes())+pad(d.getSeconds());
   var rnd=Math.random().toString(36).substring(2,6).toUpperCase();
   $('#tteDocumentNumber').val('DOC-'+stamp+'-'+rnd).trigger('change').focus();
 });
})(jQuery);
</script>
<div class="form-group"><label>Deskripsi</label><textarea name="description" class="form-control" rows="4"></textarea></div>
<div class="form-group"><label>PDF *</label><input type="file" name="pdf" accept="application/pdf,.pdf" required></div>
<button class="btn btn-primary" type="submit">Unggah & Atur Tanda Tangan</button> <a class="btn btn-default simbioAJAX" href="<?=tte_h(tte_admin_url())?>">Batal</a>
</form></div>
<iframe name="tteUploadFrame" id="tteUploadFrame" style="display:none"></iframe>
<script>
(function($){
  $('#tteUpload').off('submit.tte').on('submit.tte',function(){
    var $b=$(this).find('button[type="submit"]');
    $b.prop('disabled',true).text('Mengunggah...');
    return true;
  });
})(jQuery);
</script>
<?php elseif($view==='place'): $doc=tte_get_doc((int)($_GET['doc']??0)); if(!$doc): ?><div class="alert alert-danger">Dokumen tidak ditemukan.</div>
<?php else: ?>
<div class="sub_section"><h3>Atur Posisi QR</h3><p><b><?=tte_h($doc['document_title'])?></b> — <?=tte_h($doc['verification_id'])?></p>
<?php if(!tte_load_pdf_libs()): ?><div class="alert alert-warning"><b>mPDF bawaan SLiMS belum dapat dimuat.</b> Pastikan <code>lib/mpdf</code> dan autoloader SLiMS tersedia.</div><?php endif; ?>
<div style="display:flex;gap:20px;flex-wrap:wrap;align-items:flex-start">
 <div style="flex:1 1 700px;min-width:650px;max-width:880px">
  <div id="ttePdfWrap" style="position:relative;display:block;width:fit-content;max-width:100%;margin:0 auto;background:#666;padding:8px;line-height:0;overflow:auto;box-shadow:0 2px 10px rgba(0,0,0,.18)">
   <canvas id="ttePdfCanvas" style="display:block;background:#fff"></canvas>
   <div id="tteStamp" style="position:absolute;left:65%;top:68%;width:150px;padding:6px;background:rgba(255,255,255,.94);border:2px dashed #1677ff;cursor:grab;text-align:center;line-height:1.15;z-index:50;box-sizing:border-box;touch-action:none;user-select:none">
    <div style="width:76px;height:76px;margin:0 auto 4px;background-color:#fff;background-image:linear-gradient(90deg,#111 50%,transparent 50%),linear-gradient(#111 50%,transparent 50%);background-size:10px 10px;border:6px solid #111;box-sizing:border-box"></div>
    <small id="ttePreviewSigner"><b>Ditandatangani secara elektronik oleh:</b><br><?=tte_h($_SESSION['realname'])?></small>
    <small id="ttePreviewDatetime" style="display:block">Tanggal &amp; waktu</small>
    <small id="ttePreviewId" style="display:block">ID: <?=tte_h($doc['verification_id'])?></small>
   </div>
  </div>
  <div id="ttePdfFallback" style="display:none"><iframe src="<?=tte_h(tte_admin_url(['file'=>'original','doc'=>$doc['id'],'inline'=>1]))?>" style="width:100%;height:650px;border:1px solid #ccc"></iframe></div>
  <div id="ttePdfStatus" class="text-muted" style="margin-top:8px;line-height:1.4">Memuat preview PDF…</div>
 </div>
 <div style="width:360px;max-width:100%">
  <p>Geser kotak tanda tangan langsung di atas PDF. Pilih halaman yang akan ditandatangani. Posisi disimpan relatif agar hasil PDF final mengikuti ukuran halaman asli.</p>
  <form id="tteSignForm" method="post" action="<?=tte_h(tte_admin_url(['sign_frame'=>1]))?>" target="tteSignFrame" class="notAJAX" style="margin-top:15px" onsubmit="return ttePrepareSign(this)">
   <input type="hidden" name="mod" value="<?=tte_h(tte_plugin_mod())?>"><input type="hidden" name="id" value="<?=tte_h(tte_plugin_id())?>"><input type="hidden" name="tte_action" value="sign"><input type="hidden" name="doc" value="<?=$doc['id']?>">
   <input type="hidden" name="position_x" id="tteX" value="65"><input type="hidden" name="position_y" id="tteY" value="68"><input type="hidden" name="qr_size" id="tteSize" value="22">
   <div class="form-group"><label>Halaman yang ditandatangani</label><div class="input-group"><input id="ttePageNumber" type="number" min="1" name="page_number" value="1" class="form-control" required><span class="input-group-addon">/ <span id="ttePageCount">?</span></span></div></div>
   <div class="form-group"><label>Ukuran blok tanda tangan</label><input id="tteSizeRange" type="range" min="14" max="32" step="1" value="22" style="width:100%"><small><span id="tteSizeLabel">22</span>% lebar halaman</small></div>
   <div class="form-group" style="padding:10px 12px;border:1px solid #ddd;border-radius:4px;background:#fafafa">
    <label style="display:block;margin-bottom:7px"><b>Informasi di bawah QR</b></label>
    <label style="display:block;font-weight:normal"><input type="checkbox" name="show_signer_text" id="tteShowSigner" value="1" checked> Tampilkan “Ditandatangani secara elektronik oleh:” + nama user</label>
    <label style="display:block;font-weight:normal"><input type="checkbox" name="show_datetime" id="tteShowDatetime" value="1" checked> Tampilkan tanggal dan waktu</label>
    <label style="display:block;font-weight:normal"><input type="checkbox" name="show_tte_id" id="tteShowId" value="1" checked> Tampilkan ID TTE</label>
    <small>Semua opsi boleh dimatikan; hasil PDF akan hanya menampilkan QR Code.</small>
   </div>
   <button type="submit" class="btn btn-success">Tandatangani Dokumen</button> <a class="btn btn-default simbioAJAX" href="<?=tte_h(tte_admin_url())?>">Batal</a>
  </form>
 </div>
</div></div>
<iframe name="tteSignFrame" id="tteSignFrame" style="display:none"></iframe>
<script>
function ttePrepareSign(form){
  if(!confirm('Tandatangani dokumen ini? Setelah diproses dokumen tidak dapat diedit.')) return false;
  var $b=jQuery(form).find('button[type="submit"]');
  $b.prop('disabled',true).text('Memproses...');
  return true;
}
</script>
<script src="<?=tte_h((defined('SWB')?SWB:'/').'js/pdfjs/build/pdf.js')?>"></script>
<script>
(function(){
 var url=<?=json_encode(tte_admin_url(['file'=>'original','doc'=>$doc['id'],'inline'=>1]))?>;
 var canvas=document.getElementById('ttePdfCanvas'), wrap=document.getElementById('ttePdfWrap'), stamp=document.getElementById('tteStamp');
 var status=document.getElementById('ttePdfStatus'), pageInput=document.getElementById('ttePageNumber'), pageCount=document.getElementById('ttePageCount');
 var xInput=document.getElementById('tteX'), yInput=document.getElementById('tteY'), sizeInput=document.getElementById('tteSize'), sizeRange=document.getElementById('tteSizeRange'), sizeLabel=document.getElementById('tteSizeLabel');
 var pdf=null, dragging=false, pointerId=null, grabX=0, grabY=0;
 var savedX=parseFloat(xInput.value)||65, savedY=parseFloat(yInput.value)||68;

 function pageBox(){
   var r=canvas.getBoundingClientRect();
   return {left:r.left,top:r.top,width:r.width||canvas.width||0,height:r.height||canvas.height||0};
 }
 function setStampFromPercent(xp,yp){
   var b=pageBox();
   if(b.width<=0 || b.height<=0) return;
   var w=stamp.offsetWidth, h=stamp.offsetHeight;
   var x=(xp/100)*b.width, y=(yp/100)*b.height;
   x=Math.max(0,Math.min(x,b.width-w));
   y=Math.max(0,Math.min(y,b.height-h));
   // stamp is positioned relative to wrap; canvas begins after wrap padding.
   stamp.style.left=(canvas.offsetLeft+x)+'px';
   stamp.style.top=(canvas.offsetTop+y)+'px';
   savePercent();
 }
 function savePercent(){
   var b=pageBox();
   if(b.width<=0 || b.height<=0) return;
   var x=(parseFloat(stamp.style.left)||0)-canvas.offsetLeft;
   var y=(parseFloat(stamp.style.top)||0)-canvas.offsetTop;
   x=Math.max(0,Math.min(x,Math.max(0,b.width-stamp.offsetWidth)));
   y=Math.max(0,Math.min(y,Math.max(0,b.height-stamp.offsetHeight)));
   savedX=(x/b.width)*100; savedY=(y/b.height)*100;
   if(Number.isFinite(savedX)) xInput.value=savedX.toFixed(4);
   if(Number.isFinite(savedY)) yInput.value=savedY.toFixed(4);
   stamp.style.left=(canvas.offsetLeft+x)+'px';
   stamp.style.top=(canvas.offsetTop+y)+'px';
   status.textContent='Posisi QR: X '+xInput.value+'% • Y '+yInput.value+'% • halaman '+pageInput.value+' dari '+(pdf?pdf.numPages:'?');
 }
 function resizeStamp(){
   var b=pageBox();
   if(b.width<=0) return;
   stamp.style.width=Math.max(110,b.width*(parseFloat(sizeRange.value)/100))+'px';
   sizeInput.value=sizeRange.value; sizeLabel.textContent=sizeRange.value;
   setStampFromPercent(savedX,savedY);
 }
 function renderPage(n){
   if(!pdf)return;
   n=Math.max(1,Math.min(parseInt(n||1,10),pdf.numPages)); pageInput.value=n;
   pdf.getPage(n).then(function(page){
     // Kompatibel dengan PDF.js lama milik SLiMS dan PDF.js versi baru.
     // PDF.js lama memakai getViewport(scale), sedangkan versi baru memakai getViewport({scale: ...}).
     var v0;
     try { v0=page.getViewport({scale:1}); } catch(e) { v0=null; }
     if(!v0 || !isFinite(v0.width) || v0.width < 50) v0=page.getViewport(1);

     var parentWidth=(wrap.parentElement && wrap.parentElement.clientWidth) ? wrap.parentElement.clientWidth : 820;
     var available=Math.max(650,Math.min(850,parentWidth-10));
     var scale=available/v0.width;
     var v;
     try { v=page.getViewport({scale:scale}); } catch(e) { v=null; }
     if(!v || !isFinite(v.width) || v.width < 100) v=page.getViewport(scale);
     var dpr=window.devicePixelRatio||1;
     canvas.width=Math.floor(v.width*dpr);
     canvas.height=Math.floor(v.height*dpr);
     canvas.style.width=Math.floor(v.width)+'px';
     canvas.style.height=Math.floor(v.height)+'px';
     wrap.style.width=(Math.floor(v.width)+16)+'px';
     var ctx=canvas.getContext('2d');
     ctx.setTransform(1,0,0,1,0,0);
     return page.render({canvasContext:ctx,viewport:v,transform:dpr!==1?[dpr,0,0,dpr,0,0]:null}).promise;
   }).then(function(){
     requestAnimationFrame(function(){
       resizeStamp();
       setStampFromPercent(savedX,savedY);
       status.textContent='Preview PDF.js — halaman '+pageInput.value+' dari '+pdf.numPages+'. Geser kotak biru; posisi X/Y akan tersimpan otomatis.';
     });
   }).catch(function(e){ fallback('PDF.js gagal merender halaman: '+(e&&e.message?e.message:e)); });
 }
 function fallback(msg){
   wrap.style.display='none';
   document.getElementById('ttePdfFallback').style.display='block';
   status.textContent=msg;
 }
 var PDFAPI=window.pdfjsLib || window.PDFJS || null;
 if(PDFAPI){
   try{
     if(PDFAPI.GlobalWorkerOptions) PDFAPI.GlobalWorkerOptions.workerSrc=<?=json_encode((defined('SWB')?SWB:'/').'js/pdfjs/build/pdf.worker.js')?>;
     else PDFAPI.workerSrc=<?=json_encode((defined('SWB')?SWB:'/').'js/pdfjs/build/pdf.worker.js')?>;
   }catch(e){}
   try{
     var task=PDFAPI.getDocument(url), promise=task.promise||task;
     promise.then(function(p){pdf=p;pageCount.textContent=p.numPages;pageInput.max=p.numPages;renderPage(1);})
       .catch(function(e){fallback('PDF.js gagal memuat dokumen: '+(e&&e.message?e.message:e));});
   }catch(e){fallback('PDF.js gagal dijalankan: '+e.message);}
 } else fallback('Library PDF.js tidak ditemukan.');

 pageInput.addEventListener('change',function(){renderPage(this.value);});
 sizeRange.addEventListener('input',resizeStamp);

 stamp.addEventListener('pointerdown',function(e){
   var sr=stamp.getBoundingClientRect();
   dragging=true; pointerId=e.pointerId;
   grabX=e.clientX-sr.left; grabY=e.clientY-sr.top;
   stamp.setPointerCapture(e.pointerId);
   stamp.style.cursor='grabbing';
   e.preventDefault();
 });
 stamp.addEventListener('pointermove',function(e){
   if(!dragging || e.pointerId!==pointerId) return;
   var b=pageBox();
   if(b.width<=0 || b.height<=0) return;
   var x=e.clientX-b.left-grabX, y=e.clientY-b.top-grabY;
   x=Math.max(0,Math.min(x,b.width-stamp.offsetWidth));
   y=Math.max(0,Math.min(y,b.height-stamp.offsetHeight));
   stamp.style.left=(canvas.offsetLeft+x)+'px';
   stamp.style.top=(canvas.offsetTop+y)+'px';
   savePercent();
   e.preventDefault();
 });
 function endDrag(e){
   if(!dragging) return;
   dragging=false; stamp.style.cursor='grab';
   try{stamp.releasePointerCapture(pointerId);}catch(_){}
   pointerId=null; savePercent();
 }
 stamp.addEventListener('pointerup',endDrag);
 stamp.addEventListener('pointercancel',endDrag);

 // Jangan pernah kirim NaN/0 akibat canvas belum siap.
 ['Signer','Datetime','Id'].forEach(function(k){
   var cb=document.getElementById('tteShow'+k), el=document.getElementById('ttePreview'+k);
   if(cb&&el) cb.addEventListener('change',function(){el.style.display=this.checked?'block':'none';});
 });
 document.getElementById('tteSignForm').addEventListener('submit',function(){
   if(!Number.isFinite(parseFloat(xInput.value))) xInput.value='65';
   if(!Number.isFinite(parseFloat(yInput.value))) yInput.value='68';
 });
})();
</script>
<?php endif; ?>
<?php elseif($view==='edit'): $doc=tte_get_doc((int)($_GET['doc']??0)); if(!$doc): ?>
<div class="alert alert-danger">Dokumen tidak ditemukan.</div>
<?php else: ?>
<div class="sub_section"><h3>Edit Data Dokumen</h3>
<form method="post" action="<?=tte_h(tte_admin_url())?>" class="notAJAX" onsubmit="parent.jQuery('#mainContent').simbioAJAX(jQuery(this).attr('action'),{method:'post',addData:jQuery(this).serialize()});return false;">
<input type="hidden" name="mod" value="<?=tte_h(tte_plugin_mod())?>">
<input type="hidden" name="id" value="<?=tte_h(tte_plugin_id())?>">
<input type="hidden" name="tte_action" value="update">
<input type="hidden" name="doc" value="<?=$doc['id']?>">
<div class="form-group"><label>ID Verifikasi</label><input class="form-control" value="<?=tte_h($doc['verification_id'])?>" readonly></div>
<div class="form-group"><label>Judul Dokumen *</label><input name="document_title" class="form-control" value="<?=tte_h($doc['document_title'])?>" required></div>
<div class="form-group"><label>Nomor Dokumen</label><input name="document_number" class="form-control" value="<?=tte_h($doc['document_number'])?>"></div>
<div class="form-group"><label>Deskripsi</label><textarea name="description" class="form-control" rows="5"><?=tte_h($doc['description'])?></textarea></div>
<button type="submit" class="btn btn-primary">Simpan Perubahan</button>
<a class="btn btn-default simbioAJAX" href="<?=tte_h(tte_admin_url())?>">Batal</a>
</form></div>
<?php endif; ?>
<?php elseif($view==='detail'): $doc=tte_get_doc((int)($_GET['doc']??0)); if(!$doc): ?><div class="alert alert-danger">Dokumen tidak ditemukan.</div><?php else: ?>
<div class="sub_section"><h3>Detail Dokumen</h3><table class="table table-striped"><tr><th width="220">ID Verifikasi</th><td><?=tte_h($doc['verification_id'])?></td></tr><tr><th>Judul</th><td><?=tte_h($doc['document_title'])?></td></tr><tr><th>Nomor</th><td><?=tte_h($doc['document_number'])?></td></tr><tr><th>Deskripsi</th><td><?=nl2br(tte_h($doc['description']))?></td></tr><tr><th>Penandatangan</th><td><?=tte_h($doc['signer_name'])?></td></tr><tr><th>Waktu</th><td><?=tte_h($doc['signed_at'])?></td></tr><tr><th>Status</th><td><b><?=strtoupper(tte_h($doc['status']))?></b></td></tr><tr><th>SHA-256</th><td style="word-break:break-all"><?=tte_h($doc['document_hash'])?></td></tr><tr><th>URL Validasi</th><td><a href="<?=tte_h(tte_verify_url($doc['verification_id']))?>" target="_blank"><?=tte_h(tte_verify_url($doc['verification_id']))?></a></td></tr></table>
<?php if($doc['signed_file']): ?><a class="btn btn-primary notAJAX" href="<?=tte_h(tte_admin_url(['file'=>'signed','doc'=>$doc['id']]))?>">Download PDF</a><?php endif; ?> <a class="btn btn-default simbioAJAX" href="<?=tte_h(tte_admin_url())?>">Kembali</a>
<?php if($doc['status']==='signed' && $canWrite): ?><form method="post" action="<?=tte_h(tte_admin_url())?>" class="notAJAX" style="display:inline" onsubmit="if(!confirm('Cabut pengesahan dokumen ini?'))return false; parent.jQuery('#mainContent').simbioAJAX(jQuery(this).attr('action'),{method:'post',addData:jQuery(this).serialize()});return false;"><input type="hidden" name="mod" value="<?=tte_h(tte_plugin_mod())?>"><input type="hidden" name="id" value="<?=tte_h(tte_plugin_id())?>"><input type="hidden" name="tte_action" value="revoke"><input type="hidden" name="doc" value="<?=$doc['id']?>"><button class="btn btn-danger">Cabut Pengesahan</button></form><?php endif; ?>
</div><?php endif; ?>
<?php else:
$q=trim((string)($_GET['q']??''));
$status=trim((string)($_GET['status']??''));
$page=max(1,(int)($_GET['page']??1)); $limit=20; $offset=($page-1)*$limit;
$where=[]; $params=[];
if($q!==''){ $where[]='(verification_id LIKE ? OR document_title LIKE ? OR document_number LIKE ? OR description LIKE ? OR signer_name LIKE ?)'; for($i=0;$i<5;$i++) $params[]='%'.$q.'%'; }
if(in_array($status,['draft','signed','revoked'],true)){ $where[]='status=?'; $params[]=$status; }
$sqlWhere=$where?' WHERE '.implode(' AND ',$where):'';
$st=$db->prepare('SELECT COUNT(*) FROM tte_documents'.$sqlWhere); $st->execute($params); $total=(int)$st->fetchColumn();
$st=$db->prepare('SELECT * FROM tte_documents'.$sqlWhere.' ORDER BY id DESC LIMIT '.$limit.' OFFSET '.$offset); $st->execute($params); $rows=$st->fetchAll(PDO::FETCH_ASSOC);
$pages=max(1,(int)ceil($total/$limit));
?>
<div class="sub_section">
 <div class="btn-group" style="margin-bottom:12px">
  <?php if($canWrite): ?><a class="btn btn-primary simbioAJAX" href="<?=tte_h(tte_admin_url(['view'=>'add']))?>"><i class="fa fa-plus"></i> Tanda Tangani Dokumen</a><?php endif; ?>
 </div>

 <form method="get" action="<?=tte_h(tte_admin_url())?>" class="notAJAX form-inline" style="margin-bottom:12px" onsubmit="var u=jQuery(this).attr('action')+'&'+jQuery(this).serialize(); parent.jQuery('#mainContent').simbioAJAX(u); return false;">
  <input type="hidden" name="mod" value="<?=tte_h(tte_plugin_mod())?>">
  <input type="hidden" name="id" value="<?=tte_h(tte_plugin_id())?>">
  <div class="input-group" style="min-width:420px">
   <input type="text" name="q" value="<?=tte_h($q)?>" class="form-control" placeholder="Cari ID, judul, nomor, deskripsi, penandatangan...">
   <span class="input-group-btn"><button class="btn btn-default" type="submit"><i class="fa fa-search"></i> Cari</button></span>
  </div>
  <select name="status" class="form-control" onchange="jQuery(this).closest('form').submit()">
   <option value="">Semua Status</option>
   <option value="draft" <?=$status==='draft'?'selected':''?>>Draft</option>
   <option value="signed" <?=$status==='signed'?'selected':''?>>Signed</option>
   <option value="revoked" <?=$status==='revoked'?'selected':''?>>Revoked</option>
  </select>
  <?php if($q!==''||$status!==''): ?><a class="btn btn-default simbioAJAX" href="<?=tte_h(tte_admin_url())?>">Reset</a><?php endif; ?>
 </form>

 <div class="dataListHeader" style="padding:8px 10px;background:#f4f4f4;border:1px solid #ddd;border-bottom:0">
  <b>Daftar Dokumen TTE</b> <span class="text-muted">— <?=$total?> data</span>
 </div>
 <div class="table-responsive">
 <table class="table table-bordered table-striped table-hover" style="margin-bottom:0">
  <thead><tr>
   <th width="45">No.</th><th>ID TTE</th><th>Judul / Nomor Dokumen</th><th>Penandatangan</th><th>Waktu</th><th width="90">Status</th><th width="210">Aksi</th>
  </tr></thead><tbody>
  <?php $no=$offset+1; foreach($rows as $r): ?>
   <tr>
    <td><?=$no++?></td>
    <td><b><?=tte_h($r['verification_id'])?></b></td>
    <td><?=tte_h($r['document_title'])?><?php if($r['document_number']): ?><br><small class="text-muted"><?=tte_h($r['document_number'])?></small><?php endif; ?></td>
    <td><?=tte_h($r['signer_name'])?></td>
    <td><?=tte_h($r['signed_at']?:$r['created_at'])?></td>
    <td><span class="label <?=$r['status']==='signed'?'label-success':($r['status']==='revoked'?'label-danger':'label-warning')?>"><?=strtoupper(tte_h($r['status']))?></span></td>
    <td style="white-space:nowrap">
     <a class="btn btn-xs btn-default simbioAJAX" href="<?=tte_h(tte_admin_url(['view'=>$r['status']==='draft'?'place':'detail','doc'=>$r['id']]))?>"><i class="fa fa-eye"></i> <?=$r['status']==='draft'?'Lanjutkan':'Detail'?></a>
     <?php if($canWrite): ?>
      <a class="btn btn-xs btn-primary simbioAJAX" href="<?=tte_h(tte_admin_url(['view'=>'edit','doc'=>$r['id']]))?>"><i class="fa fa-pencil"></i> Edit</a>
      <form method="post" action="<?=tte_h(tte_admin_url())?>" class="notAJAX" style="display:inline" onsubmit="if(!confirm('Hapus dokumen ini beserta file PDF-nya?')) return false; parent.jQuery('#mainContent').simbioAJAX(jQuery(this).attr('action'),{method:'post',addData:jQuery(this).serialize()}); return false;">
       <input type="hidden" name="mod" value="<?=tte_h(tte_plugin_mod())?>"><input type="hidden" name="id" value="<?=tte_h(tte_plugin_id())?>">
       <input type="hidden" name="tte_action" value="delete"><input type="hidden" name="doc" value="<?=$r['id']?>">
       <button type="submit" class="btn btn-xs btn-danger"><i class="fa fa-trash"></i> Delete</button>
      </form>
     <?php endif; ?>
    </td>
   </tr>
  <?php endforeach; if(!$rows): ?><tr><td colspan="7" class="text-center text-muted" style="padding:25px">Data dokumen tidak ditemukan.</td></tr><?php endif; ?>
  </tbody>
 </table></div>
 <?php if($pages>1): ?><div style="padding:10px 0">
  <?php for($i=1;$i<=$pages;$i++): ?>
   <a class="btn btn-xs <?=$i===$page?'btn-primary':'btn-default'?> simbioAJAX" href="<?=tte_h(tte_admin_url(['q'=>$q,'status'=>$status,'page'=>$i]))?>"><?=$i?></a>
  <?php endfor; ?>
 </div><?php endif; ?>
</div>
<?php endif; ?>
