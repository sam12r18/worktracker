<?php
namespace App\Http\Controllers\WorkTracker;
use App\Http\Controllers\Controller; use App\Models\{Customer,Invoice}; use App\Services\{InvoiceExcelExportService,InvoiceService}; use Carbon\CarbonImmutable; use Illuminate\Http\Request;
class InvoiceController extends Controller
{
    public function index(Request $r){$uid=$r->user()->id;return view('worktracker.invoices.index',['customers'=>Customer::where('user_id',$uid)->where('is_active',true)->orderBy('name')->get(),'invoices'=>Invoice::where('user_id',$uid)->with('customer')->latest()->paginate(30)]);}
    public function generate(Request $r,InvoiceService $service){$d=$r->validate(['customer_id'=>['required','uuid'],'period_start'=>['required','date'],'period_end'=>['required','date','after:period_start']]);$c=Customer::where('user_id',$r->user()->id)->findOrFail($d['customer_id']);$i=$service->createOrRebuildDraft($r->user()->id,$c,CarbonImmutable::parse($d['period_start'])->startOfDay(),CarbonImmutable::parse($d['period_end'])->addDay()->startOfDay());return redirect()->route('worktracker.invoices.show',$i)->with('status','پیش‌نویس فاکتور ساخته/بازسازی شد.');}
    public function show(Request $r,Invoice $invoice){$this->own($r,$invoice);return view('worktracker.invoices.show',['invoice'=>$invoice->load(['customer','items.project','items.activityType'])]);}
    public function update(Request $r,Invoice $invoice,InvoiceService $service){$this->own($r,$invoice);$d=$r->validate(['adjustment_minor'=>['nullable','integer'],'tax_minor'=>['nullable','integer','min:0'],'notes'=>['nullable','string','max:5000']]);$service->updateDraftTotals($invoice,(int)($d['adjustment_minor']??0),(int)($d['tax_minor']??0),$d['notes']??null);return back()->with('status','مقادیر پیش‌نویس ذخیره شد.');}
    public function finalize(Request $r,Invoice $invoice,InvoiceService $service){$this->own($r,$invoice);$service->finalize($invoice);return back()->with('status','فاکتور نهایی شد؛ Snapshot قیمت‌ها ثابت شد.');}
    public function excel(Request $r,Invoice $invoice,InvoiceExcelExportService $export){$this->own($r,$invoice);$name=($invoice->number ?: 'draft-'.$invoice->id).'.xls';return response($export->xml($invoice),200,['Content-Type'=>'application/vnd.ms-excel; charset=UTF-8','Content-Disposition'=>'attachment; filename="'.$name.'"']);}
    public function print(Request $r,Invoice $invoice){$this->own($r,$invoice);return view('worktracker.invoices.print',['invoice'=>$invoice->load(['customer','items.project','items.activityType'])]);}
    private function own(Request $r,Invoice $i):void{abort_unless((string)$i->user_id===(string)$r->user()->id,404);}
}
