<?php
namespace App\Services;
use App\Models\Invoice;
class InvoiceExcelExportService
{
    public function xml(Invoice $invoice): string
    {
        $invoice->loadMissing(['customer','items.project','items.activityType']);
        $esc=fn($v)=>htmlspecialchars((string)$v,ENT_XML1|ENT_QUOTES,'UTF-8');
        $rows=''; foreach($invoice->items as $i){ $rows.='<Row><Cell><Data ss:Type="String">'.$esc($i->started_at?->format('Y-m-d H:i')).'</Data></Cell><Cell><Data ss:Type="String">'.$esc($i->project?->name).'</Data></Cell><Cell><Data ss:Type="String">'.$esc($i->activityType?->name).'</Data></Cell><Cell><Data ss:Type="String">'.$esc($i->description).'</Data></Cell><Cell><Data ss:Type="Number">'.round($i->billable_seconds/3600,4).'</Data></Cell><Cell><Data ss:Type="Number">'.$i->effective_rate_minor.'</Data></Cell><Cell><Data ss:Type="Number">'.$i->amount_minor.'</Data></Cell></Row>'; }
        return '<?xml version="1.0" encoding="UTF-8"?><?mso-application progid="Excel.Sheet"?><Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"><Worksheet ss:Name="Invoice"><Table><Row><Cell><Data ss:Type="String">تاریخ</Data></Cell><Cell><Data ss:Type="String">پروژه</Data></Cell><Cell><Data ss:Type="String">فعالیت</Data></Cell><Cell><Data ss:Type="String">شرح</Data></Cell><Cell><Data ss:Type="String">ساعت</Data></Cell><Cell><Data ss:Type="String">نرخ</Data></Cell><Cell><Data ss:Type="String">مبلغ</Data></Cell></Row>'.$rows.'</Table></Worksheet></Workbook>';
    }
}
