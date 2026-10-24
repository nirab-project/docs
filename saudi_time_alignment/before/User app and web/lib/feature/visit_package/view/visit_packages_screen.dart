import 'package:nirab_clean/common/widgets/nirab/nirab_icon.dart';
import 'package:nirab_clean/common/widgets/nirab/nirab_foundation.dart';
import 'package:nirab_clean/util/core_export.dart';
import 'package:get/get.dart';

class VisitPackagesScreen extends StatefulWidget {
  const VisitPackagesScreen({super.key});
  @override
  State<VisitPackagesScreen> createState() => _VisitPackagesScreenState();
}

class _VisitPackagesScreenState extends State<VisitPackagesScreen> {
  ApiClient get _api => Get.find<ApiClient>();
  List<dynamic> _packages = [], _plans = [];
  int _packagePage = 1, _packageLast = 1, _planPage = 1, _planLast = 1;
  bool _busy = false;
  String? _error;
  String? _plansError;
  String? _packagesError;

  bool _eligibleForVisit(Map package) {
    final expiry = DateTime.tryParse(package['expires_at']?.toString() ?? '');
    final remaining = int.tryParse('${package['remaining_visits']}') ?? 0;
    return package['status'] == 'active' &&
        remaining > 0 &&
        expiry != null &&
        expiry.isAfter(DateTime.now());
  }

  String _displayStatus(Map package) {
    final expires = DateTime.tryParse(package['expires_at']?.toString() ?? '');
    if (package['status'] == 'active' &&
        expires != null &&
        !expires.isAfter(DateTime.now())) {
      return 'expired';
    }
    return package['status']?.toString() ?? 'suspended';
  }

  bool _canAutoRenew(Map package) {
    final cadence = package['pricing_snapshot']?['cadence']?.toString();
    // Permit disabling a previously enabled renewal even after the end date.
    return (cadence == 'weekly' || cadence == 'monthly') &&
        package['renewal_at'] != null &&
        package['status'] == 'active' &&
        (_eligibleForVisit(package) || package['auto_renew'] == true);
  }

  @override
  void initState() {
    super.initState();
    if (Get.find<AuthController>().isLoggedIn()) _load();
  }

  Future<dynamic> _content(Future<Response> request) async {
    final response = await request;
    if (response.statusCode != 200 && response.statusCode != 201) {
      throw StateError(
        (response.body is Map ? response.body['message'] : null)?.toString() ??
            'quality_failed'.tr,
      );
    }
    return response.body['content'];
  }

  Future<void> _perform(Future<void> Function() action) async {
    if (_busy) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await action();
    } catch (error) {
      if (mounted) setState(() => _error = error.toString());
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _loadData() async {
    // Keep purchased visits visible even if the public sales catalog fails.
    String? packagesError, plansError;
    List<dynamic>? packages, plans;
    int? packageLast, planLast;
    try {
      final response = await _content(
        _api.getData('/api/v1/customer/visit-packages?page=$_packagePage'),
      );
      packages = List<dynamic>.from(response['data'] as List? ?? []);
      packageLast = (response['last_page'] as num? ?? 1).toInt();
    } catch (error) {
      packagesError = error.toString();
    }
    try {
      final response = await _content(
        _api.getData('/api/v1/customer/visit-packages/plans?page=$_planPage'),
      );
      plans = List<dynamic>.from(response['data'] as List? ?? []);
      planLast = (response['last_page'] as num? ?? 1).toInt();
    } catch (error) {
      plansError = error.toString();
    }
    if (!mounted) return;
    setState(() {
      _packagesError = packagesError;
      _plansError = plansError;
      if (packages != null) {
        _packages = packages;
        _packageLast = packageLast ?? 1;
      }
      if (plans != null) {
        _plans = plans;
        _planLast = planLast ?? 1;
      }
    });
  }

  Future<void> _load() => _perform(_loadData);

  Future<void> _buy(Map plan) async {
    final accepted = await Get.dialog<bool>(
      AlertDialog(
        title: Text('quality_buy_package'.tr),
        content: Text(
          '${plan['name']}\n${plan['price']} ${plan['currency']}\n${'ux_package_wallet_only'.tr}',
        ),
        actions: [
          TextButton(
            onPressed: () => Get.back(result: false),
            child: Text('cancel'.tr),
          ),
          TextButton(
            onPressed: () => Get.back(result: true),
            child: Text('confirm'.tr),
          ),
        ],
      ),
    );
    if (accepted != true) return;
    await _perform(() async {
      final prefs = Get.find<SharedPreferences>();
      final customerId = Get.find<UserController>().userInfoModel?.id;
      if (customerId == null) throw StateError('ux_customer_not_ready'.tr);
      final storageKey = 'ux_package_purchase_${customerId}_${plan['id']}';
      final key = prefs.getString(storageKey) ?? const Uuid().v4();
      await prefs.setString(storageKey, key);
      await _content(
        _api.postData('/api/v1/customer/visit-packages/purchase', {
          'plan_id': plan['id'],
          'idempotency_key': key,
        }),
      );
      await prefs.remove(storageKey);
      await _loadData();
      // The purchase is committed; a balance refresh must not prompt a retry.
      try {
        await Get.find<UserController>().getUserInfo(reload: true);
      } catch (_) {
        if (mounted) customSnackBar('ux_wallet_balance_refresh_failed'.tr);
      }
    });
  }

  Future<void> _ledger(String id) async {
    int page = 1;
    while (mounted) {
      final rows = await _content(
        _api.getData('/api/v1/customer/visit-packages/$id/ledger?page=$page'),
      );
      if (!mounted) return;
      final next = await showDialog<int>(
        context: context,
        builder: (_) => AlertDialog(
          title: Text('quality_ledger'.tr),
          content: SizedBox(
            width: 460,
            child: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  if ((rows['data'] as List).isEmpty)
                    Text('quality_no_data'.tr),
                  for (final row in rows['data'] as List)
                    ListTile(
                      title: Text('quality_ledger_${row['type']}'.tr),
                      subtitle: Text('${row['created_at']}'),
                      trailing: Text('${row['delta']} / ${row['balance']}'),
                      onTap: row['booking_id'] == null
                          ? null
                          : () {
                              Navigator.pop(context);
                              Get.toNamed(
                                RouteHelper.getBookingDetailsScreen(
                                  bookingID: row['booking_id'].toString(),
                                ),
                              );
                            },
                    ),
                ],
              ),
            ),
          ),
          actions: [
            if (page > 1)
              TextButton(
                onPressed: () => Navigator.pop(context, page - 1),
                child: Text('quality_previous'.tr),
              ),
            TextButton(
              onPressed: () => Navigator.pop(context),
              child: Text('quality_close'.tr),
            ),
            if (page < (rows['last_page'] as num).toInt())
              TextButton(
                onPressed: () => Navigator.pop(context, page + 1),
                child: Text('quality_next'.tr),
              ),
          ],
        ),
      );
      if (next == null) return;
      page = next;
    }
  }

  Widget _pager(int page, int last, void Function(int) change) => Wrap(
    alignment: WrapAlignment.center,
    crossAxisAlignment: WrapCrossAlignment.center,
    children: [
      TextButton(
        onPressed: _busy || page <= 1
            ? null
            : () {
                change(page - 1);
                _load();
              },
        child: Text('quality_previous'.tr),
      ),
      Text('$page / $last'),
      TextButton(
        onPressed: _busy || page >= last
            ? null
            : () {
                change(page + 1);
                _load();
              },
        child: Text('quality_next'.tr),
      ),
    ],
  );

  @override
  Widget build(BuildContext context) => !Get.find<AuthController>().isLoggedIn()
      ? Scaffold(
          appBar: CustomAppBar(title: 'ux_my_packages'.tr),
          body: Center(
            child: Padding(
              padding: const EdgeInsets.all(20),
              child: NirabGuestState(
                title: 'login_to_continue'.tr,
                message: 'ux_package_wallet_only'.tr,
                signInLabel: 'sign_in'.tr,
                onSignIn: () async {
                  await Get.toNamed(
                    RouteHelper.getSignInRoute(
                      redirectUrl: RouteHelper.visitPackages,
                    ),
                  );
                  if (mounted && Get.find<AuthController>().isLoggedIn()) {
                    _load();
                  }
                },
              ),
            ),
          ),
        )
      : Scaffold(
          appBar: CustomAppBar(title: 'ux_my_packages'.tr),
          body: SafeArea(
            child: Center(
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 900),
                child: RefreshIndicator(
                  onRefresh: _load,
                  child: ListView(
                    padding: const EdgeInsets.all(16),
                    children: [
                      if (_busy) const LinearProgressIndicator(),
                      if (_error != null)
                        ListTile(
                          title: Text(_error!),
                          trailing: TextButton(
                            onPressed: _busy ? null : _load,
                            child: Text('retry'.tr),
                          ),
                        ),
                      if (Get.find<SplashController>()
                              .configModel
                              .content
                              ?.isProFeatureActive ==
                          1)
                        Card(
                          child: ListTile(
                            leading: const NirabIcon(
                              Icons.workspace_premium_outlined,
                            ),
                            title: Text('ux_pro_membership'.tr),
                            subtitle: Text('ux_pro_membership_hint'.tr),
                            trailing: const NirabIcon(Icons.chevron_right),
                            onTap: () => Get.toNamed(
                              Get.find<UserController>()
                                          .userInfoModel
                                          ?.subscribedPlan !=
                                      null
                                  ? RouteHelper.getMySubscriptionDetailsRoute()
                                  : RouteHelper.getSubscriptionRoute(),
                            ),
                          ),
                        ),
                      NirabInfoPanel(message: 'ux_package_wallet_only'.tr),
                      GetBuilder<UserController>(builder: (userController) {
                        final balance = userController.userInfoModel?.walletBalance;
                        return TextButton.icon(
                          icon: const NirabIcon(
                            Icons.account_balance_wallet_outlined,
                          ),
                          label: Text(balance == null
                              ? 'my_wallet'.tr
                              : '${'my_wallet'.tr} (${PriceConverter.convertPrice(balance)})'),
                          onPressed: () async {
                            await Get.toNamed(RouteHelper.getMyWalletScreen());
                            if (mounted) {
                              await _load();
                              try {
                                await Get.find<UserController>().getUserInfo(reload: true);
                              } catch (_) {
                                if (mounted) customSnackBar('ux_wallet_balance_refresh_failed'.tr);
                              }
                            }
                          },
                        );
                      }),
                      NirabSectionHeader(title: 'quality_packages'.tr),
                      if (_packagesError != null)
                        ListTile(
                          title: Text(_packagesError!),
                          trailing: TextButton(
                            onPressed: _busy ? null : _load,
                            child: Text('retry'.tr),
                          ),
                        ),
                      if (!_busy && _packagesError == null && _packages.isEmpty)
                        Padding(
                          padding: const EdgeInsets.all(16),
                          child: Text('quality_no_data'.tr),
                        ),
                      for (final package in _packages)
                        Card(
                          child: Padding(
                            padding: const EdgeInsets.all(12),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  '${package['pricing_snapshot']?['name'] ?? ''}',
                                  style: robotoBold,
                                ),
                                Text(
                                  '${'quality_remaining_visits'.tr}: ${package['remaining_visits']} / ${package['total_visits']}',
                                ),
                                Text(
                                  '${'ux_package_expiry'.tr}: ${package['expires_at']}',
                                ),
                                Text(
                                  '${'status'.tr}: ${'ux_package_status_${_displayStatus(package)}'.tr}',
                                ),
                                Text(
                                  '${'ux_package_units'.tr}: ${package['pricing_snapshot']?['pricing_policy']?['max_units_per_visit'] ?? 1}',
                                ),
                                Text(
                                  '${'ux_package_interval'.tr}: ${package['pricing_snapshot']?['pricing_policy']?['minimum_interval_days'] ?? 0}',
                                ),
                                Wrap(
                                  spacing: 8,
                                  children: [
                                    TextButton(
                                      onPressed:
                                          _busy || !_eligibleForVisit(package)
                                          ? null
                                          : () async {
                                              await Get.toNamed(
                                                RouteHelper.getPackageVisitRoute(
                                                  package['id'].toString(),
                                                ),
                                              );
                                              if (mounted) _load();
                                            },
                                      child: Text('quality_reserve_visit'.tr),
                                    ),
                                    TextButton(
                                      onPressed: _busy
                                          ? null
                                          : () => _perform(
                                              () => _ledger(
                                                package['id'].toString(),
                                              ),
                                            ),
                                      child: Text('quality_ledger'.tr),
                                    ),
                                  ],
                                ),
                                if (_canAutoRenew(package))
                                  SwitchListTile(
                                    contentPadding: EdgeInsets.zero,
                                    title: Text('quality_auto_renew'.tr),
                                    subtitle: Text('quality_renewal_policy'.tr),
                                    value: package['auto_renew'] == true,
                                    onChanged: _busy
                                        ? null
                                        : (value) => _perform(() async {
                                            await _content(
                                              _api.putData(
                                                '/api/v1/customer/visit-packages/${package['id']}/renewal',
                                                {'auto_renew': value},
                                              ),
                                            );
                                            await _loadData();
                                          }),
                                  ),
                              ],
                            ),
                          ),
                        ),
                      _pager(
                        _packagePage,
                        _packageLast,
                        (value) => _packagePage = value,
                      ),
                      Text('quality_refund_policy'.tr),
                      const SizedBox(height: 24),
                      NirabSectionHeader(title: 'quality_plans'.tr),
                      if (_plansError != null)
                        ListTile(
                          title: Text(_plansError!),
                          trailing: TextButton(
                            onPressed: _busy ? null : _load,
                            child: Text('retry'.tr),
                          ),
                        ),
                      if (!_busy && _plansError == null && _plans.isEmpty)
                        Text('quality_no_data'.tr),
                      for (final plan in _plans)
                        Card(
                          child: Padding(
                            padding: const EdgeInsets.all(16),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.stretch,
                              children: [
                                Text(
                                  '${plan['translations']?[Get.locale?.languageCode] ?? plan['name']}',
                                  style: Theme.of(
                                    context,
                                  ).textTheme.titleMedium,
                                ),
                                const SizedBox(height: 8),
                                Text(
                                  '${plan['visits']} ${'checkout_visits'.tr} · ${plan['price']} ${plan['currency']}',
                                ),
                                Text(
                                  '${'ux_package_valid_days'.tr}: ${plan['validity_days']}',
                                ),
                                Text(
                                  '${'ux_package_units'.tr}: ${plan['pricing_policy']?['max_units_per_visit'] ?? 1}',
                                ),
                                const SizedBox(height: 16),
                                NirabActionBar(
                                  label: 'quality_buy_package'.tr,
                                  onPressed:
                                      _busy ||
                                          Get.find<SplashController>()
                                                  .configModel
                                                  .content
                                                  ?.walletStatus !=
                                              1
                                      ? null
                                      : () => _buy(plan),
                                ),
                              ],
                            ),
                          ),
                        ),
                      _pager(
                        _planPage,
                        _planLast,
                        (value) => _planPage = value,
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        );
}
