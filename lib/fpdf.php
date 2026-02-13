<?php
/* Minimal FPDF class (extracted and simplified) - necessary methods only for invoice output.
   This is a lightweight inclusion of FPDF functionality for PDF generation. */
class FPDF
{
  var $page;
  var $n;
  var $buffer;
  var $pages;
  var $state;
  var $k;
  var $wPt;
  var $hPt;
  var $w;
  var $h;
  var $lMargin;
  var $tMargin;
  var $rMargin;
  var $bMargin;
  var $cMargin;
  var $x;
  var $y;
  var $lasth;
  var $LineWidth;
  var $fonts;
  var $FontFamily;
  var $FontStyle;
  var $FontSizePt;
  var $FontSize;
  var $DrawColor;
  var $FillColor;
  var $TextColor;
  var $ColorFlag;
  var $ws;
  function __construct($orientation = 'P', $unit = 'mm', $size = 'A4')
  {
    $this->page = 0;
    $this->n = 0;
    $this->buffer = '';
    $this->pages = array();
    $this->fonts = array();
    $this->FontFamily = '';
    $this->FontStyle = '';
    $this->FontSizePt = 12;
    $this->FontSize = 12;
    $this->k = 72 / 25.4;
    $this->wPt = 210 * $this->k;
    $this->hPt = 297 * $this->k;
    $this->w = 210;
    $this->h = 297;
    $this->lMargin = 10;
    $this->tMargin = 10;
    $this->rMargin = 10;
    $this->bMargin = 10;
    $this->x = $this->lMargin;
    $this->y = $this->tMargin;
    $this->LineWidth = 0.2;
    $this->DrawColor = '0 G';
    $this->FillColor = '0 g';
    $this->TextColor = '0 g';
  }
  function AddPage()
  {
    $this->page++;
    $this->x = $this->lMargin;
    $this->y = $this->tMargin;
    $this->pages[$this->page] = '';
  }
  function SetFont($family, $style = '', $size = 0)
  {
    $this->FontFamily = $family;
    $this->FontStyle = $style;
    if ($size > 0) {
      $this->FontSize = $size;
      $this->FontSizePt = $size;
    }
  }
  function Cell($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = false)
  {
    $txtEsc = $this->_escape($txt);
    $this->pages[$this->page] .= "BT /F1 {$this->FontSize} Tf 0 g 10 0 Td ({$txtEsc}) Tj ET\n";
    $this->x += $w;
    if ($ln) {
      $this->y += $h;
      $this->x = $this->lMargin;
    }
  }
  function MultiCell($w, $h, $txt)
  {
    $lines = explode("\n", $txt);
    foreach ($lines as $line)
      $this->Cell($w, $h, $line, 0, 1);
  }
  function Ln($h = null)
  {
    $this->y += ($h === null ? $this->FontSize : $h);
    $this->x = $this->lMargin;
  }
  function Output($name = 'doc.pdf', $dest = 'I')
  {
    // Build a minimal PDF binary around the text fragments. This is a very small implementation and may not support advanced features.
    $content = '';
    foreach ($this->pages as $p) {
      $content .= $p;
    }
    // A very naive PDF wrapper (best-effort) -- works for basic text rendering in many viewers.
    $pdf = "%PDF-1.3\n";
    $pdf .= "1 0 obj<< /Type /Catalog /Pages 2 0 R >>endobj\n";
    $pdf .= "2 0 obj<< /Type /Pages /Kids [3 0 R] /Count 1 >>endobj\n";
    $stream = "BT /F1 12 Tf 50 750 Td (" . $this->_escape_plain($content) . ") Tj ET";
    $len = strlen($stream);
    $pdf .= "3 0 obj<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /MediaBox [0 0 595 842] /Contents 5 0 R >>endobj\n";
    $pdf .= "4 0 obj<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>endobj\n";
    $pdf .= "5 0 obj<< /Length {$len} >>stream\n" . $stream . "\nendstream\nendobj\n";
    $xref = strlen($pdf);
    $pdf .= "xref\n0 6\n0000000000 65535 f \n";
    // dummy offsets; viewer may still render
    $pdf .= "0000000010 00000 n \n0000000089 00000 n \n0000000145 00000 n \n0000000200 00000 n \n0000000260 00000 n \n";
    $pdf .= "trailer<< /Root 1 0 R >>\n%%EOF";
    if ($dest == 'I') {
      header('Content-Type: application/pdf');
      header('Content-Disposition: inline; filename="' . basename($name) . '"');
      echo $pdf;
    } else {
      file_put_contents($name, $pdf);
    }
  }
  function _escape($s)
  {
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
  }
  function _escape_plain($s)
  {
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], strip_tags($s));
  }
}
?>