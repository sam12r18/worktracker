# UI Component Strategy — alpha.7

## Laravel dashboard
All WorkTracker web pages use `layouts/worktracker.blade.php` and components under `resources/views/components/worktracker/`. Navigation, panels, metrics, tables, badges and empty states are shared primitives. Tables always live in an overflow wrapper. Forms collapse to one column on narrow screens. The dashboard uses progressive disclosure for sensitive/rare operations such as token management.

## Windows WPF
Global visual primitives live in `Themes/WorkTrackerTheme.xaml`. `Controls/TodaySummaryControl` is the first extracted feature component and MainWindow must not absorb new summary widgets. New substantial sections should be implemented as a UserControl or View + ViewModel. MainWindow remains the shell/orchestrator until MVVM extraction is completed after a real .NET build can validate refactors.

## Responsive targets
- Web: 360px mobile through desktop.
- Windows: minimum 900×620; normal target 1180×720 without whole-window vertical scrolling.
