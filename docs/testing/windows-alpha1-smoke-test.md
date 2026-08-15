# Windows Agent alpha.1 smoke test

Prerequisites: Windows 10/11 and .NET 10 SDK.

1. Run `powershell -ExecutionPolicy Bypass -File .\tools\build-windows-agent.ps1`.
2. Run `powershell -ExecutionPolicy Bypass -File .\tools\run-windows-agent.ps1`.
3. Confirm `%LOCALAPPDATA%\WorkTracker\worktracker.db` is created.
4. Focus Notepad for at least 5 seconds, then focus another application. Confirm the Notepad segment appears in Timeline.
5. Leave the PC idle for more than 5 minutes. Confirm the idle interval is not added to the prior foreground session.
6. Click `توقف ردیابی`, use another application, then resume. Confirm paused time is absent.
7. Enter a manual note and start a manual timer. Confirm automatic foreground tracking continues and both streams remain active.
8. Close the main window with X. Confirm WorkTracker remains in the system tray.
9. Open it again from the tray and confirm prior Timeline rows remain.
10. Exit completely and relaunch. Confirm device identity and local records persist.

Expected accounting invariant: two separate installations/devices may contain overlapping sessions. Server reporting must preserve both; no cross-device deduplication is allowed.


## Concurrent activity checks

1. Keep auto tracking active in an editor.
2. Start manual timer A and let both run for at least two minutes.
3. Start manual timer B without stopping A.
4. Verify all three streams continue independently.
5. Stop A and B individually.
6. Verify Effort is greater than Elapsed Coverage and no source record was shortened or deleted to normalize the total.
7. For the same project, verify overlapping records remain separate and additive.


## Concurrent activity checks

1. Keep auto tracking active in an editor.
2. Start manual timer A and let both run for at least two minutes.
3. Start manual timer B without stopping A.
4. Verify all three streams continue independently.
5. Stop A and B individually.
6. Verify Effort is greater than Elapsed Coverage and no source record was shortened or deleted to normalize the total.
7. For the same project, verify overlapping records remain separate and additive.
