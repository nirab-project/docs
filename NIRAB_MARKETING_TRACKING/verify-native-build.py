from pathlib import Path
import importlib.util, sys, json
sys.dont_write_bytecode = True
root = Path(__file__).resolve().parents[2] / 'User app and web'
spec = importlib.util.spec_from_file_location('marketing_build', root / 'tool/prepare_marketing_build.py')
m = importlib.util.module_from_spec(spec)
spec.loader.exec_module(m)
base = json.loads((root / 'config/marketing.native.example.json').read_text())
checks = 0
for platform in ['android', 'ios']:
    defines, native = m.prepare(base, platform, 'staging', {})
    assert not defines['MKT_MOBILE_ENABLED']
    assert 'NIRAB_FACEBOOK_CLIENT_TOKEN' not in defines
    checks += 2
for change in [{'MKT_STAGING': False}, {'MKT_FIREBASE_ENABLED': True}, {'MKT_META_ENABLED': 'true'}, {'MARKETING_META_TOKEN': 'forbidden'}, {'MKT_META_APP_ID': 'YOUR_APP_ID'}]:
    try:
        m.prepare({**base, **change}, 'android', 'staging', {})
        raise AssertionError('Invalid config accepted')
    except ValueError:
        checks += 1
meta = {**base, 'MKT_MOBILE_ENABLED': True, 'MKT_META_ENABLED': True, 'MKT_META_APP_ID': '123456789'}
try:
    m.prepare(meta, 'android', 'staging', {})
    raise AssertionError('Missing Meta identity accepted')
except ValueError:
    checks += 1
_, native = m.prepare(meta, 'ios', 'staging', {'NIRAB_FACEBOOK_APP_ID': '123456789', 'NIRAB_FACEBOOK_CLIENT_TOKEN': 'a' * 32})
assert native['NIRAB_FACEBOOK_APP_ID'] == '123456789'
checks += 1
tiktok = {**base, 'MKT_MOBILE_ENABLED': True, 'MKT_TIKTOK_ENABLED': True, 'MKT_TIKTOK_IOS_APP_ID': 'com.example.customer', 'MKT_TIKTOK_IOS_ID': '123456789'}
_, native = m.prepare(tiktok, 'ios', 'staging', {})
assert native['NIRAB_TIKTOK_URL_SCHEME'] == 'tiktok123456789'
checks += 1
try:
    m.prepare(tiktok, 'android', 'staging', {})
    raise AssertionError('Wrong platform IDs accepted')
except ValueError:
    checks += 1
print(f'PASS: {checks} Native preparation checks; no builds, network or generated configuration files.')
