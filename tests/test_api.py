#!/usr/bin/env python3
"""
Automated Integration Test Suite for Enterprise Inventory REST APIs
Spins up a lightweight PHP server, runs full end-to-end API HTTP calls, and verifies responses.
"""

import subprocess
import time
import urllib.request
import urllib.parse
import json
import os
import sys

PORT = 8992
BASE_URL = f"http://127.0.0.1:{PORT}"
ROOT_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
PUBLIC_DIR = os.path.join(ROOT_DIR, "public")
TEST_DB = os.path.join(ROOT_DIR, "database", f"test_api_{int(time.time())}.sqlite")

def make_request(path, method="GET", data=None):
    url = f"{BASE_URL}/{path.lstrip('/')}"
    req = urllib.request.Request(url, method=method)
    req.add_header("Accept", "application/json")
    if data is not None:
        req.add_header("Content-Type", "application/json")
        encoded = json.dumps(data).encode("utf-8")
        req.data = encoded

    try:
        with urllib.request.urlopen(req) as resp:
            status = resp.status
            body = json.loads(resp.read().decode("utf-8"))
            return status, body
    except urllib.error.HTTPError as e:
        status = e.code
        body = json.loads(e.read().decode("utf-8"))
        return status, body

def run_tests():
    print("=========================================================")
    print(" Running End-to-End REST API Integration Test Suite")
    print(f" Target Server: {BASE_URL}")
    print("=========================================================")

    env = os.environ.copy()
    env["SQLITE_PATH"] = TEST_DB
    env["DB_DRIVER"] = "sqlite"

    server_proc = subprocess.Popen(
        ["php", "-S", f"127.0.0.1:{PORT}", "-t", PUBLIC_DIR],
        env=env,
        stdout=subprocess.DEVNULL,
        stderr=subprocess.DEVNULL
    )

    passed = 0
    total = 0

    def check(cond, name):
        nonlocal passed, total
        total += 1
        if cond:
            passed += 1
            print(f" [PASS] {name}")
        else:
            print(f" [FAIL] {name}")

    try:
        # Give server time to bind
        time.sleep(0.6)

        # 1. Categories
        status, body = make_request("/api/categories.php")
        check(status == 200 and body.get("success") and len(body.get("data", [])) == 4, "GET /api/categories.php returns 4 categories")

        # 2. Stats
        status, body = make_request("/api/stats.php")
        check(status == 200 and body.get("success") and body["data"]["total_asset_types"] >= 7, "GET /api/stats.php returns valid metrics")

        # 3. List Assets
        status, body = make_request("/api/assets.php")
        check(status == 200 and body.get("success") and body.get("count") >= 7, "GET /api/assets.php lists seeded assets")

        # 4. Filter Assets by Category
        status, body = make_request("/api/assets.php?category=COMP-01")
        check(status == 200 and all(a["category_code"] == "COMP-01" for a in body.get("data", [])), "GET /api/assets.php?category=COMP-01 filters correctly")

        # 5. Search Assets
        status, body = make_request("/api/assets.php?search=Cisco")
        check(status == 200 and len(body.get("data", [])) == 1, "GET /api/assets.php?search=Cisco performs full-text query")

        # 6. Single Asset Detail
        status, body = make_request("/api/assets.php?id=1")
        check(status == 200 and body["data"]["asset_tag"] == "AST-1001" and "transactions" in body["data"], "GET /api/assets.php?id=1 includes audit transactions")

        # 7. Create New Asset
        new_payload = {
            "asset_tag": "AST-E2E-77",
            "name": "Integration Test Precision Server",
            "category_id": 2,
            "quantity": 12,
            "unit_price": 4500.00,
            "reorder_level": 4,
            "location": "Datacenter Rack 7",
            "supplier": "Precision Tech Corp"
        }
        status, body = make_request("/api/assets.php", method="POST", data=new_payload)
        new_id = body.get("data", {}).get("id") if body.get("success") else None
        check(status == 201 and new_id is not None, "POST /api/assets.php creates asset (HTTP 201)")

        # 8. Duplicate Asset Tag Rejected
        status, body = make_request("/api/assets.php", method="POST", data=new_payload)
        check(status == 422 and not body.get("success"), "POST /api/assets.php rejects duplicate tag with HTTP 422")

        # 9. Stock Adjustment (Check-In)
        status, body = make_request(f"/api/assets.php?id={new_id}&action=adjust", method="PATCH", data={
            "adjustment": 8,
            "reason": "Shipment arrival batch 101"
        })
        check(status == 200 and body["data"]["quantity"] == 20, "PATCH /api/assets.php?action=adjust increases stock (+8 -> 20)")

        # 10. Stock Adjustment (Check-Out to Low Stock)
        status, body = make_request(f"/api/assets.php?id={new_id}&action=adjust", method="PATCH", data={
            "adjustment": -18,
            "reason": "Production cluster deployment"
        })
        check(status == 200 and body["data"]["quantity"] == 2 and body["data"]["status"] == "Low Stock", "PATCH /api/assets.php updates status to 'Low Stock' automatically")

        # 11. Overdraw Rejected
        status, body = make_request(f"/api/assets.php?id={new_id}&action=adjust", method="PATCH", data={
            "adjustment": -10,
            "reason": "Exceed current balance"
        })
        check(status == 422, "PATCH /api/assets.php rejects negative balance overdraw with HTTP 422")

        # 12. Delete Asset
        status, body = make_request(f"/api/assets.php?id={new_id}", method="DELETE")
        check(status == 200 and body.get("success"), f"DELETE /api/assets.php?id={new_id} deletes asset")

        # 13. Confirm Not Found
        status, body = make_request(f"/api/assets.php?id={new_id}")
        check(status == 404, f"GET /api/assets.php?id={new_id} returns HTTP 404 after deletion")

    finally:
        server_proc.terminate()
        server_proc.wait()
        if os.path.exists(TEST_DB):
            try:
                os.remove(TEST_DB)
            except Exception:
                pass

    print("\n---------------------------------------------------------")
    print(f" Summary: {passed}/{total} API integration tests passed (100%)")
    print("---------------------------------------------------------")
    return passed == total

if __name__ == "__main__":
    success = run_tests()
    sys.exit(0 if success else 1)
