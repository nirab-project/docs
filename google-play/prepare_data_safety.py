"""Prepare reviewable Play Console CSVs from its exported schema (no user records)."""

import argparse
import csv
from pathlib import Path

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument("--workspace", type=Path, default=Path.cwd(),
                    help="Workspace containing provider-app and service-man-app")
parser.add_argument("--template", type=Path,
                    default=Path.home() / "Downloads" / "data_safety_export.csv",
                    help="Blank CSV exported by Google Play Console")
args = parser.parse_args()
ROOT = args.workspace.resolve()
TEMPLATE = args.template.resolve()
DELETE_URL = "https://nirab-clean-apps-privacy.super-goat-3024.chatgpt.site/delete-account.html"
Q, R, V = "Question ID (machine readable)", "Response ID (machine readable)", "Response value"
FUNCTION = "PSL_APP_FUNCTIONALITY"
ACCOUNT = "PSL_ACCOUNT_MANAGEMENT"
SECURITY = "PSL_FRAUD_PREVENTION_SECURITY"
ANALYTICS = "PSL_ANALYTICS"

# Required means account/core operation data; optional means feature/permission dependent.
# Transfers include the related service parties; no advertising purposes are selected.
COMMON = {
    "PSL_NAME": (True, [FUNCTION, ACCOUNT], True),
    "PSL_EMAIL": (True, [FUNCTION, ACCOUNT], False),
    "PSL_USER_ACCOUNT": (True, [FUNCTION, ACCOUNT, SECURITY], False),
    "PSL_PHONE": (True, [FUNCTION, ACCOUNT], True),
    "PSL_OTHER_PERSONAL": (False, [ACCOUNT, SECURITY], False),
    "PSL_OTHER": (False, [FUNCTION, SECURITY], False),
    "PSL_APPROX_LOCATION": (False, [FUNCTION], False),
    "PSL_PRECISE_LOCATION": (False, [FUNCTION], False),
    "PSL_OTHER_MESSAGES": (False, [FUNCTION], True),
    "PSL_PHOTOS": (False, [FUNCTION, ACCOUNT], True),
    "PSL_FILES_AND_DOCS": (False, [FUNCTION], True),
    "PSL_USER_INTERACTION": (True, [FUNCTION], False),
    "PSL_IN_APP_SEARCH_HISTORY": (False, [FUNCTION], False),
    "PSL_USER_GENERATED_CONTENT": (False, [FUNCTION], True),
    "PSL_DEVICE_ID": (True, [FUNCTION, SECURITY], False),
}

with TEMPLATE.open(encoding="utf-8-sig", newline="") as source:
    reader = csv.DictReader(source)
    columns = reader.fieldnames
    template = list(reader)

for app in ["provider-app", "service-man-app"]:
    data = dict(COMMON)
    if app == "provider-app":
        data["PSL_NAME"] = (True, [FUNCTION, ACCOUNT], False)
        data["PSL_PHONE"] = (True, [FUNCTION, ACCOUNT], False)
        data.update({
            "PSL_ADDRESS": (False, [FUNCTION, ACCOUNT], False),
            "PSL_CREDIT_DEBIT_BANK_ACCOUNT_NUMBER": (False, [FUNCTION], False),
            "PSL_PURCHASE_HISTORY": (False, [FUNCTION], False),
            "PSL_CRASH_LOGS": (False, [ANALYTICS], True),
            "PSL_PERFORMANCE_DIAGNOSTICS": (False, [ANALYTICS], True),
            "PSL_DEVICE_ID": (True, [FUNCTION, SECURITY, ANALYTICS], True),
            "PSL_USER_INTERACTION": (True, [FUNCTION, ANALYTICS], True),
        })
    else:
        data["PSL_PRECISE_LOCATION"] = (False, [FUNCTION], True)

    values = {
        ("PSL_DATA_COLLECTION_COLLECTS_PERSONAL_DATA", ""): "true",
        ("PSL_DATA_COLLECTION_ENCRYPTED_IN_TRANSIT", ""): "true",
        ("PSL_SUPPORTED_ACCOUNT_CREATION_METHODS", "PSL_ACM_USER_ID_PASSWORD"): "true",
        ("PSL_ACCOUNT_DELETION_URL", ""): DELETE_URL,
    }
    if app == "provider-app":
        values[("PSL_SUPPORTED_ACCOUNT_CREATION_METHODS", "PSL_ACM_USER_ID_PASSWORD_OTHER_AUTH")] = "true"

    for row in template:
        if row[Q].startswith("PSL_DATA_TYPES_") and row[R] in data:
            values[(row[Q], row[R])] = "true"

    for kind, (required, purposes, shared) in data.items():
        prefix = "PSL_DATA_USAGE_RESPONSES:" + kind + ":"
        values[(prefix + "PSL_DATA_USAGE_COLLECTION_AND_SHARING", "PSL_DATA_USAGE_ONLY_COLLECTED")] = "true"
        if shared:
            values[(prefix + "PSL_DATA_USAGE_COLLECTION_AND_SHARING", "PSL_DATA_USAGE_ONLY_SHARED")] = "true"
        values[(prefix + "PSL_DATA_USAGE_EPHEMERAL", "")] = "false"
        control = "PSL_DATA_USAGE_USER_CONTROL_REQUIRED" if required else "PSL_DATA_USAGE_USER_CONTROL_OPTIONAL"
        values[(prefix + "DATA_USAGE_USER_CONTROL", control)] = "true"
        for purpose in purposes:
            values[(prefix + "DATA_USAGE_COLLECTION_PURPOSE", purpose)] = "true"
        if shared:
            sharing_purposes = [ANALYTICS] if kind in {"PSL_CRASH_LOGS", "PSL_PERFORMANCE_DIAGNOSTICS"} else [FUNCTION]
            if app == "provider-app" and kind in {"PSL_DEVICE_ID", "PSL_USER_INTERACTION"}:
                sharing_purposes = [FUNCTION, ANALYTICS]
            for purpose in sharing_purposes:
                values[(prefix + "DATA_USAGE_SHARING_PURPOSE", purpose)] = "true"

    rows = [dict(row, **{V: values.get((row[Q], row[R]), "")}) for row in template]
    known = {(row[Q], row[R]) for row in rows}
    assert set(values) <= known, sorted(set(values) - known)
    selected = {row[R] for row in rows if row[Q].startswith("PSL_DATA_TYPES_") and row[V] == "true"}
    assert selected == set(data), (app, selected ^ set(data))
    target = ROOT / app / "docs" / "store-listing" / "data-safety-draft.csv"
    with target.open("w", encoding="utf-8", newline="") as output:
        writer = csv.DictWriter(output, fieldnames=columns)
        writer.writeheader()
        writer.writerows(rows)
    print(f"{app}: {len(data)} data types; {len(values)} responses; {target}")
