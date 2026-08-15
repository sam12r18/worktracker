using System.Windows;
namespace WorkTracker.Agent.Controls;
public partial class TodaySummaryControl : System.Windows.Controls.UserControl {
 public TodaySummaryControl(){InitializeComponent();}
 public static readonly DependencyProperty CurrentActivityProperty=DependencyProperty.Register(nameof(CurrentActivity),typeof(string),typeof(TodaySummaryControl),new PropertyMetadata("هنوز فعالیتی تشخیص داده نشده"));
 public static readonly DependencyProperty CurrentProcessProperty=DependencyProperty.Register(nameof(CurrentProcess),typeof(string),typeof(TodaySummaryControl),new PropertyMetadata("-"));
 public static readonly DependencyProperty EffortProperty=DependencyProperty.Register(nameof(Effort),typeof(string),typeof(TodaySummaryControl),new PropertyMetadata("00:00:00"));
 public static readonly DependencyProperty CoverageProperty=DependencyProperty.Register(nameof(Coverage),typeof(string),typeof(TodaySummaryControl),new PropertyMetadata("00:00:00"));
 public static readonly DependencyProperty ConcurrentProperty=DependencyProperty.Register(nameof(Concurrent),typeof(string),typeof(TodaySummaryControl),new PropertyMetadata("00:00:00"));
 public static readonly DependencyProperty UnknownSyncProperty=DependencyProperty.Register(nameof(UnknownSync),typeof(string),typeof(TodaySummaryControl),new PropertyMetadata("0 / 0"));
 public string CurrentActivity{get=>(string)GetValue(CurrentActivityProperty);set=>SetValue(CurrentActivityProperty,value);} public string CurrentProcess{get=>(string)GetValue(CurrentProcessProperty);set=>SetValue(CurrentProcessProperty,value);} public string Effort{get=>(string)GetValue(EffortProperty);set=>SetValue(EffortProperty,value);} public string Coverage{get=>(string)GetValue(CoverageProperty);set=>SetValue(CoverageProperty,value);} public string Concurrent{get=>(string)GetValue(ConcurrentProperty);set=>SetValue(ConcurrentProperty,value);} public string UnknownSync{get=>(string)GetValue(UnknownSyncProperty);set=>SetValue(UnknownSyncProperty,value);}
}
