# alpha.7.3 Activity Intelligence Smoke Test

## 1. PhpStorm file switching

1. Ensure a Project Rule can classify `Ketabnow2` from PhpStorm title.
2. Open at least four files in the same PhpStorm workspace for 5–10 seconds each.
3. Open WPF `Timeline امروز`.

Expected: raw capture may contain multiple sessions, but WPF shows one Work Event for the contiguous Project context and `جزئیات` shows multiple aggregated segments.

## 2. Same Project across apps

1. Work in PhpStorm on Project A for at least 2 minutes.
2. Open a browser page that is also classified as Project A for 30–90 seconds.
3. Return to PhpStorm Project A.

Expected: one Project A Work Event; browser time is direct time and is not double-counted.

## 3. Short cross-Project bridge

1. Work on Project A for at least 2 minutes.
2. Switch to a correctly classified Project B for 20–120 seconds.
3. Return to Project A.

Expected: Project A event shows `مستقیم ... + تداوم ...`; Project B keeps its own direct event; Effort exceeds Coverage by the bridge duration.

## 4. Long interruption

Repeat test 3 but remain on Project B for more than 120 seconds.

Expected: no bridge; Project A is split into separate events.

## 5. Anti-oscillation

After a valid A→B→A bridge, switch back to B before accumulating another 120 seconds of direct A work, then return to A within 120 seconds.

Expected: the second interruption is not bridged automatically.

## 6. Unknown IDE aggregation + learning

1. Disable/delete the Project Rule for a PhpStorm workspace.
2. Switch among several files.
3. In `تشخیص‌داده‌نشده`, select the aggregated event and choose `انتساب + یادگیری`.

Expected: all raw sessions in the event are assigned together and the learned WindowTitle Rule uses the stable workspace name rather than the current file name.

## 7. Web Rule Builder

1. Open a Project page.
2. Paste `Ketabnow2 – README.md` into `نمونه عنوان پنجره`.
3. Click `پیشنهاد الگوی پایدار`.

Expected: `WindowTitle / contains / Ketabnow2` is suggested and the 7-day preview reports same-project, other-project and unknown matches.

## 8. Activity Type inference safety

- A plain PhpStorm title must remain without an automatically inferred Development type.
- If an explicit Debug/Debugger title signal exists and an active Debug/دیباگ Activity Type exists locally, that type may be inferred.


## 9. Browser learning safety

1. Select an event whose browser title contains the selected Project name/code and use `انتساب + یادگیری`.
2. Confirm the learned WindowTitle Rule uses that stable Project hint, not the entire tab title.
3. Repeat with a browser title that contains no safe Project hint.

Expected: correction is still applied, but the Agent does not create a broad Chrome/Edge process rule or an unsafe exact-tab learning rule. Use the Web Rule Builder preview to define the pattern manually.
