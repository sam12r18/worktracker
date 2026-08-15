<?php
namespace App\Services;
use App\Models\{ActivitySession,BillingRateSnapshot,Customer,Invoice,InvoiceItem}; use Carbon\CarbonImmutable; use Illuminate\Database\QueryException; use Illuminate\Support\Facades\DB;
class InvoiceService
{
    public function __construct(private PricingService $pricing) {}
    public function createOrRebuildDraft(int|string $userId, Customer $customer, CarbonImmutable $from, CarbonImmutable $to): Invoice
    {
        abort_unless((string)$customer->user_id===(string)$userId,404);
        return DB::transaction(function() use($userId,$customer,$from,$to){
            $inclusiveEnd=$to->subDay()->toDateString();
            abort_if(Invoice::query()->where('user_id',$userId)->where('customer_id',$customer->id)->where('period_start',$from->toDateString())->where('period_end',$inclusiveEnd)->where('status','final')->exists(),409,'A finalized invoice already exists for this customer and period.');
            $invoice=Invoice::query()->where('user_id',$userId)->where('customer_id',$customer->id)->where('period_start',$from->toDateString())->where('period_end',$inclusiveEnd)->where('status','draft')->lockForUpdate()->first();
            $periodCurrency=$this->pricing->customerCurrencyAt($customer, $from);
            $invoice ??= Invoice::create(['user_id'=>$userId,'customer_id'=>$customer->id,'status'=>'draft','period_start'=>$from->toDateString(),'period_end'=>$inclusiveEnd,'currency'=>$periodCurrency]);
            $invoice->currency=$periodCurrency;
            $invoice->items()->delete(); $subtotal=0; $untyped=0; $nonbillable=0;
            $sessions=ActivitySession::query()->where('user_id',$userId)->with(['project','activityType'])->where('started_at','<',$to)->where('ended_at','>',$from)->whereNotIn('id', \App\Models\InvoiceItem::query()->whereHas('invoice',fn($q)=>$q->where('status','final'))->select('activity_session_id'))->orderBy('started_at')->get();
            foreach($sessions as $session){
                if(!$session->project || $this->pricing->customerIdAt($session->project, $session->started_at) !== $customer->id) continue;
                if(!$session->activity_type_id){$untyped++;continue;}
                $r=$this->pricing->resolve($session); if($r['billable_seconds']<=0){$nonbillable++;continue;} abort_if($r['currency']!==$invoice->currency,422,'Mixed-currency invoice requires explicit conversion support.');
                $overlapStart=$session->started_at->greaterThan($from)?$session->started_at:$from; $overlapEnd=$session->ended_at->lessThan($to)?$session->ended_at:$to;
                $billableSeconds=max(0,$overlapStart->diffInSeconds($overlapEnd)); if($billableSeconds<=0) continue;
                $amount=(int)round($r['effective_rate_minor']*($billableSeconds/3600));
                $description=trim(($session->activityType?->name ?? 'فعالیت').' — '.($session->project?->name ?? 'بدون پروژه').($session->note?' — '.$session->note:''));
                $invoice->items()->create(['activity_session_id'=>$session->id,'project_id'=>$session->project_id,'activity_type_id'=>$session->activity_type_id,'description'=>$description,'started_at'=>$overlapStart,'billable_seconds'=>$billableSeconds,'base_rate_minor'=>$r['base_rate_minor'],'customer_multiplier'=>$r['customer_multiplier'],'project_multiplier'=>$r['project_multiplier'],'effective_rate_minor'=>$r['effective_rate_minor'],'amount_minor'=>$amount,'currency'=>$r['currency'],'resolution_source'=>$r['resolution_source'],'pricing_override_id'=>$r['pricing_override_id']]);
                $subtotal += $amount;
            }
            $invoice->untyped_activity_count=$untyped;$invoice->nonbillable_activity_count=$nonbillable;$invoice->subtotal_minor=$subtotal; $invoice->total_minor=max(0,$subtotal+(int)$invoice->adjustment_minor+(int)$invoice->tax_minor); $invoice->save(); return $invoice->fresh(['customer','items.project','items.activityType']);
        });
    }
    public function updateDraftTotals(Invoice $invoice, int $adjustment, int $tax, ?string $notes): Invoice
    { abort_if($invoice->status!=='draft',409,'Final invoice is immutable.'); $invoice->adjustment_minor=$adjustment;$invoice->tax_minor=max(0,$tax);$invoice->notes=$notes;$invoice->total_minor=max(0,(int)$invoice->subtotal_minor+$adjustment+max(0,$tax));$invoice->save();return $invoice; }
    public function finalize(Invoice $invoice): Invoice
    {
        abort_if($invoice->status!=='draft',409,'Invoice is not a draft.');
        return DB::transaction(function() use($invoice){ $invoice=Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail(); abort_if($invoice->status!=='draft',409,'Invoice is not a draft.'); $invoice->load('items');
            $activityIds=$invoice->items->pluck('activity_session_id');
            $alreadyFinal=\App\Models\InvoiceItem::query()->whereIn('activity_session_id',$activityIds)->where('invoice_id','!=',$invoice->id)->whereHas('invoice',fn($q)=>$q->where('status','final'))->lockForUpdate()->first();
            abort_if($alreadyFinal,409,'At least one activity was finalized on another invoice. Rebuild this draft before finalization.');
            try {
                foreach($invoice->items as $item) BillingRateSnapshot::create(['activity_session_id'=>$item->activity_session_id,'base_rate_minor'=>$item->base_rate_minor,'customer_multiplier'=>$item->customer_multiplier,'project_multiplier'=>$item->project_multiplier,'effective_rate_minor'=>$item->effective_rate_minor,'billable_seconds'=>$item->billable_seconds,'amount_minor'=>$item->amount_minor,'currency'=>$item->currency,'resolution_source'=>$item->resolution_source,'pricing_override_id'=>$item->pricing_override_id]);
            } catch (QueryException $e) {
                abort(409, 'At least one activity was finalized concurrently. Rebuild this draft and try again.');
            }
            $invoice->number='WT-'.$invoice->period_start->format('Ym').'-'.strtoupper(substr(str_replace('-','',$invoice->id),0,12)); $invoice->status='final';$invoice->finalized_at=now();$invoice->save(); return $invoice->fresh(['customer','items.project','items.activityType']); });
    }
}
