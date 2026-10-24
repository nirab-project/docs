import 'package:nirab_clean/common/widgets/nirab/nirab_foundation.dart';
import 'package:nirab_clean/common/widgets/nirab/nirab_service_card.dart';
import 'package:nirab_clean/feature/home/marketing/marketing_feed.dart';
import 'package:nirab_clean/feature/home/marketing/marketing_feed_content.dart';
import 'package:nirab_clean/feature/home/model/campaign_model.dart';
import 'package:nirab_clean/util/core_export.dart';
import 'package:get/get.dart';

class CampaignView extends StatelessWidget {
  const CampaignView({super.key, this.homePreview = true});
  final bool homePreview;
  @override
  Widget build(BuildContext context) => GetBuilder<CampaignController>(
    builder: (controller) {
      final feed = controller.feed;
      if (homePreview &&
          [
            MarketingStatus.empty,
            MarketingStatus.noZone,
            MarketingStatus.unavailable,
          ].contains(feed.status)) {
        return const SizedBox.shrink();
      }
      return Padding(
        padding: EdgeInsets.only(
          top: homePreview ? BrandTokens.spacing24 : 0,
          bottom: homePreview ? BrandTokens.spacing16 : 0,
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (homePreview) ...[
              NirabSectionHeader(
                title: 'nirab_campaigns'.tr,
                action: TextButton(
                  onPressed: () =>
                      Get.toNamed(RouteHelper.getOffersRoute(tab: 'campaigns')),
                  child: Text('nirab_view_all_campaigns'.tr),
                ),
              ),
              const SizedBox(height: 12),
            ],
            MarketingFeedContent<CampaignData>(
              feed: feed,
              paginate: !homePreview,
              emptyKey: 'nirab_campaigns_empty',
              child: NirabAdaptiveGrid(
                minWidth: 260,
                children: [
                  for (final campaign
                      in homePreview ? feed.items.take(4) : feed.items)
                    NirabCampaignCard(
                      campaign: campaign,
                      onOpen: () =>
                          controller.navigateFromCampaign(campaign.id!, ''),
                    ),
                ],
              ),
            ),
          ],
        ),
      );
    },
  );
}

class NirabCampaignCard extends StatelessWidget {
  const NirabCampaignCard({
    super.key,
    required this.campaign,
    required this.onOpen,
  });
  final CampaignData campaign;
  final VoidCallback onOpen;
  @override
  Widget build(BuildContext context) => NirabPanel(
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Semantics(
          label: campaign.campaignName ?? 'nirab_campaigns'.tr,
          button: true,
          child: InkWell(
            onTap: onOpen,
            borderRadius: BorderRadius.circular(BrandTokens.cardRadius),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(BrandTokens.cardRadius),
              child: AspectRatio(
                aspectRatio: 2,
                child: CustomImage(
                  image: campaign.coverImageFullPath ?? '',
                  fit: BoxFit.cover,
                  placeholder: Images.placeholder,
                ),
              ),
            ),
          ),
        ),
        const SizedBox(height: 12),
        Text(
          campaign.campaignName?.trim().isNotEmpty == true
              ? campaign.campaignName!
              : 'nirab_campaigns'.tr,
          style: Theme.of(context).textTheme.titleMedium,
        ),
        if (campaign.discount != null)
          CampaignTerms(campaign: campaign, compact: true),
        const SizedBox(height: 12),
        OutlinedButton(
          onPressed: onOpen,
          child: Text('nirab_campaign_services'.tr),
        ),
      ],
    ),
  );
}

/// Informational API terms only; no price calculation or eligibility decision.
class CampaignTerms extends StatelessWidget {
  const CampaignTerms({
    super.key,
    required this.campaign,
    this.compact = false,
  });
  final CampaignData campaign;
  final bool compact;
  @override
  Widget build(BuildContext context) {
    final discount = campaign.discount;
    if (discount == null) return const SizedBox.shrink();
    final terms = <String>[];
    final amount = discount.discountAmount;
    if (amount != null &&
        amount >= 0 &&
        ['percent', 'amount'].contains(discount.discountAmountType)) {
      terms.add(
        '${'nirab_campaign_discount'.tr}: ${discount.discountAmountType == 'percent' ? '$amount%' : PriceConverter.convertPrice(amount, decimalPointCount: 2)}',
      );
    }
    if (discount.startDate != null &&
        DateTime.tryParse(discount.startDate!) != null) {
      terms.add('${'nirab_campaign_start'.tr}: ${discount.startDate}');
    }
    if (discount.endDate != null &&
        DateTime.tryParse(discount.endDate!) != null) {
      terms.add('${'nirab_campaign_end'.tr}: ${discount.endDate}');
    }
    if (!compact) {
      if (discount.minPurchase != null) {
        terms.add(
          '${'nirab_campaign_minimum'.tr}: ${PriceConverter.convertPrice(discount.minPurchase, decimalPointCount: 2)}',
        );
      }
      if (discount.maxDiscountAmount != null) {
        terms.add(
          '${'nirab_campaign_maximum'.tr}: ${PriceConverter.convertPrice(discount.maxDiscountAmount, decimalPointCount: 2)}',
        );
      }
      if (discount.limitPerUser != null) {
        terms.add('${'nirab_campaign_limit'.tr}: ${discount.limitPerUser}');
      }
    }
    if (terms.isEmpty) return const SizedBox.shrink();
    return Padding(
      padding: const EdgeInsets.only(top: 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (!compact)
            Text(
              'nirab_campaign_terms'.tr,
              style: Theme.of(context).textTheme.titleMedium,
            ),
          for (final term in terms)
            Padding(padding: const EdgeInsets.only(top: 4), child: Text(term)),
        ],
      ),
    );
  }
}
