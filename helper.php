<?php
/*
 * File: helper.php
 * Created on Thu Oct 01 2026
 * Last Updated: Thu Oct 01 2026 5:52:59 PM
 * Author: Erwan Setyo Budi
 * Email: erwans818@gmail.com
 * License: The GNU General Public License, Version 3 (GPL-3.0) - Copyright (C) 2026 Erwan Setyo Budi. This program is free software.
 */

use SLiMS\DB;
function tte_h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function tte_db(){ return DB::getInstance(); }
function tte_plugin_id(){ return $_GET['id'] ?? $_POST['id'] ?? ''; }
function tte_plugin_mod(){ return $_GET['mod'] ?? $_POST['mod'] ?? 'system'; }
function tte_admin_url(array $extra=[]){
    $q=array_merge(['mod'=>tte_plugin_mod(),'id'=>tte_plugin_id()],$extra);
    return AWB.'plugin_container.php?'.http_build_query($q);
}
function tte_storage($kind){
    $dir=__DIR__.'/storage/'.($kind==='signed'?'signed':'original');
    if(!is_dir($dir)) @mkdir($dir,0755,true);
    return $dir;
}
function tte_random_id(){ return 'TTE-'.date('Ymd').'-'.strtoupper(bin2hex(random_bytes(4))); }
function tte_base_url(){
    $https=(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') || (($_SERVER['HTTP_X_FORWARDED_PROTO']??'')==='https');
    $scheme=$https?'https':'http';
    $host=$_SERVER['HTTP_HOST']??'localhost';
    $swb=defined('SWB')?SWB:'/';
    if(preg_match('~^https?://~i',$swb)) return rtrim($swb,'/').'/';
    return $scheme.'://'.$host.'/'.trim($swb,'/').(trim($swb,'/')!==''?'/':'');
}
function tte_verify_url($code){ return tte_base_url().'index.php?p=tte-validation&code='.rawurlencode($code); }

/**
 * v1.0.2: gunakan mPDF bawaan SLiMS. Jangan melakukan class_exists()
 * terhadap adapter FPDI TCPDF karena itu dapat memicu fatal error saat TCPDF tidak ada.
 */
function tte_load_pdf_libs(){
    if(class_exists('\\Mpdf\\Mpdf', false)) return true;

    // Autoloader utama SLiMS biasanya sudah aktif. Daftar ini hanya fallback aman.
    $candidates=[];
    if(defined('SB')){
        $candidates[] = SB.'vendor/autoload.php';
        $candidates[] = SB.'lib/autoload.php';
    }
    $candidates[] = __DIR__.'/vendor/autoload.php';
    foreach(array_unique($candidates) as $f){
        if($f && is_file($f)) require_once $f;
        if(class_exists('\\Mpdf\\Mpdf', false)) return true;
    }

    // Sekarang aman meminta autoloader mencari mPDF saja; tidak menyentuh FPDI-TCPDF.
    return class_exists('\\Mpdf\\Mpdf');
}
function tte_get_doc($id){
    $st=tte_db()->prepare('SELECT * FROM tte_documents WHERE id=? LIMIT 1');
    $st->execute([(int)$id]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}
function tte_stream_file($doc,$kind='signed',$inline=false){
    $field=$kind==='original'?'original_file':'signed_file';
    if(!$doc || empty($doc[$field])){ http_response_code(404); exit('File tidak ditemukan'); }
    $path=tte_storage($kind).'/'.basename($doc[$field]);
    if(!is_file($path)){ http_response_code(404); exit('File tidak ditemukan'); }
    while(ob_get_level()) @ob_end_clean();
    header('Content-Type: application/pdf');
    header('Content-Length: '.filesize($path));
    header('Content-Disposition: '.($inline?'inline':'attachment').'; filename="'.basename($path).'"');
    header('X-Content-Type-Options: nosniff');
    readfile($path); exit;
}

function tte_sign_pdf($doc,$page,$xPct,$yPct,$stampWidthPct,$showSignerText=true,$showDatetime=true,$showTteId=true){
    $xPct=is_numeric($xPct)?(float)$xPct:65.0;
    $yPct=is_numeric($yPct)?(float)$yPct:68.0;
    $stampWidthPct=is_numeric($stampWidthPct)?(float)$stampWidthPct:22.0;
    $xPct=max(0.0,min(100.0,$xPct));
    $yPct=max(0.0,min(100.0,$yPct));
    $stampWidthPct=max(14.0,min(32.0,$stampWidthPct));
    if(!tte_load_pdf_libs()){
        throw new RuntimeException('mPDF bawaan SLiMS tidak dapat dimuat. Pastikan folder lib/mpdf dan autoloader SLiMS tersedia.');
    }
    // mPDF 8 memisahkan generator QR ke package mpdf/qrcode.
    // Cek sebelum proses panjang agar error dapat dikembalikan ke UI plugin dengan jelas.
    if(!class_exists('\\Mpdf\\QrCode\\QrCode')){
        throw new RuntimeException('Library QR mPDF belum tersedia. Jalankan: composer require mpdf/qrcode');
    }
    $src=tte_storage('original').'/'.basename($doc['original_file']);
    if(!is_file($src)) throw new RuntimeException('PDF asli tidak ditemukan.');

    $signedAt=date('Y-m-d H:i:s');
    $url=tte_verify_url($doc['verification_id']);

    // Temp directory lokal plugin menghindari masalah permission pada tmp global.
    $tmp=__DIR__.'/storage/tmp';
    if(!is_dir($tmp)) @mkdir($tmp,0755,true);

    $mpdf=new \Mpdf\Mpdf([
        'tempDir'=>$tmp,
        'mode'=>'utf-8',
        'margin_left'=>0,'margin_right'=>0,'margin_top'=>0,'margin_bottom'=>0,
        'margin_header'=>0,'margin_footer'=>0,
    ]);
    $mpdf->SetAutoPageBreak(false,0);

    try{
        $count=$mpdf->SetSourceFile($src);
    }catch(\Throwable $e){
        $em=$e->getMessage();
        if(stripos($em,'compression technique')!==false || stripos($em,'free parser')!==false){
            throw new RuntimeException('PDF memakai kompresi/object stream yang tidak didukung parser FPDI gratis. Solusi: buka PDF lalu Print/Save as PDF kembali (mis. Microsoft Print to PDF/Chrome), kemudian unggah hasil PDF baru. File asli tidak rusak; keterbatasan ada pada parser FPDI gratis.');
        }
        throw $e;
    }
    $page=max(1,min((int)$page,(int)$count));

    for($i=1;$i<=$count;$i++){
        $tpl=$mpdf->ImportPage($i);
        $size=$mpdf->getTemplateSize($tpl);
        $w=(float)$size['width'];
        $h=(float)$size['height'];
        $orientation=$w>$h?'L':'P';

        // Ukuran halaman custom mengikuti PDF sumber agar tidak berubah skala.
        $mpdf->AddPageByArray([
            'orientation'=>$orientation,
            'sheet-size'=>[$w,$h],
            'margin-left'=>0,'margin-right'=>0,'margin-top'=>0,'margin-bottom'=>0,
            'margin-header'=>0,'margin-footer'=>0,
        ]);
        $mpdf->UseTemplate($tpl,0,0,$w,$h,true);

        if($i===$page){
            $stampW=max(38,min(62,$w*((float)$stampWidthPct/100)));
            $stampH=max(31,min(48,$stampW*0.82));
            $x=max(2,min($w-$stampW-2,$w*((float)$xPct/100)));
            $y=max(2,min($h-$stampH-2,$h*((float)$yPct/100)));

            $signer=tte_h($doc['signer_name']);
            $vid=tte_h($doc['verification_id']);
            $vurl=tte_h($url);
            $time=tte_h(date('d-m-Y H:i:s',strtotime($signedAt)).' WIB');

            // QR dibuat langsung oleh barcode engine mPDF, tanpa TCPDF/library QR tambahan.
            $info='';
            if($showSignerText) $info.='<b>Ditandatangani secara elektronik oleh:</b><br>'.$signer;
            if($showDatetime){ if($info!=='') $info.='<br>'; $info.=$time; }
            if($showTteId){ if($info!=='') $info.='<br>'; $info.='<b>ID:</b> '.$vid; }

            $html='<div style="font-family: sans-serif; font-size:6.5pt; line-height:1.15; text-align:center; background-color:#ffffff; padding:1mm;">'
                .'<div style="text-align:center"><barcode code="'.$vurl.'" type="QR" size="0.75" error="M" disableborder="1" /></div>'
                .($info!==''?'<div style="margin-top:0.8mm;text-align:center">'.$info.'</div>':'')
                .'</div>';
            $mpdf->WriteFixedPosHTML($html,$x,$y,$stampW,$stampH,'auto');
        }
    }

    $name=$doc['verification_id'].'.pdf';
    $dest=tte_storage('signed').'/'.$name;
    $mpdf->Output($dest,\Mpdf\Output\Destination::FILE);
    if(!is_file($dest) || filesize($dest)<100) throw new RuntimeException('PDF hasil gagal dibuat.');

    return [
        'file'=>$name,
        'signed_at'=>$signedAt,
        'hash'=>hash_file('sha256',$dest),
        'page'=>$page
    ];
}
