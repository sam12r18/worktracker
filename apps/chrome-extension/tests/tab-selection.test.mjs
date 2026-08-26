import test from "node:test";
import assert from "node:assert/strict";
import { queryForCandidate } from "../src/tab-selection.js";

test("uses the active tab in Chrome's last-focused window without trusting window.focused", async () => {
  const calls = [];
  const chromeApi = {
    windows: { WINDOW_ID_NONE: -1 },
    tabs: {
      async query(queryInfo) {
        calls.push(queryInfo);
        return [{ id: 41, windowId: 9001, active: true, title: "WorkTracker" }];
      }
    }
  };

  const result = await queryForCandidate(chromeApi);

  assert.deepEqual(calls, [{ active: true, lastFocusedWindow: true }]);
  assert.equal(result.tab?.id, 41);
  assert.equal(result.windowId, 9001);
});

test("uses an explicit Chrome window id from focus/tab events", async () => {
  const calls = [];
  const chromeApi = {
    windows: { WINDOW_ID_NONE: -1 },
    tabs: {
      async query(queryInfo) {
        calls.push(queryInfo);
        return [{ id: 77, windowId: 1234, active: true }];
      }
    }
  };

  const result = await queryForCandidate(chromeApi, 1234);

  assert.deepEqual(calls, [{ active: true, windowId: 1234 }]);
  assert.equal(result.tab?.id, 77);
  assert.equal(result.windowId, 1234);
});

test("returns no candidate when Chrome exposes no active tab", async () => {
  const chromeApi = {
    windows: { WINDOW_ID_NONE: -1 },
    tabs: { async query() { return []; } }
  };

  const result = await queryForCandidate(chromeApi);

  assert.equal(result.tab, null);
  assert.equal(result.windowId, null);
});
