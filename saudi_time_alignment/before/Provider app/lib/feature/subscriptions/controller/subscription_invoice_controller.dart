import 'dart:io';
import 'package:demandium_provider/helper/date_converter.dart';
import 'package:demandium_provider/feature/profile/model/provider_model.dart';
import 'package:demandium_provider/feature/subscriptions/model/subscription_transaction_model.dart';
import 'package:demandium_provider/util/dimensions.dart';
import 'package:demandium_provider/util/images.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';
import 'package:demandium_provider/feature/booking_details/controller/pdf_controller.dart';
import 'package:demandium_provider/feature/splash/controller/splash_controller.dart';
import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;
import 'package:pdf/widgets.dart';
import 'package:http/http.dart' as http;

class SubscriptionInvoice {
  static Future<File> generateInvoice({
    required SubscriptionTransactionModel transaction,
    ProviderInfo? provider,
  }) async {
    final pdf = Document();
    ImageProvider? imageProvider;
    Uint8List? imageData;
    try {
      final ByteData image = await rootBundle.load(Images.placeholder);
      imageData = (image).buffer.asUint8List();
      imageProvider = await downloadImage(
        Get.find<SplashController>().configModel.content!.logoFullPath ?? "",
      );
    } catch (e) {
      if (kDebugMode) {
        debugPrint('Operation failed: ${e.runtimeType}');
      }
    }

    pdf.addPage(
      MultiPage(
        build: (context) => [
          buildInvoiceHeader(
            imageProvider,
            imageData,
            transaction,
            provider: provider,
          ),
          buildInvoiceBody(transaction, provider: provider),
        ],
        footer: (context) => buildFooter(),
        pageTheme: pw.PageTheme(
          buildBackground: (_) {
            return pw.Container(
              decoration: BoxDecoration(color: PdfColor.fromHex("f9fcff")),
            );
          },
          pageFormat: PdfPageFormat.a4.copyWith(
            marginBottom: 0,
            marginLeft: 0,
            marginRight: 0,
            marginTop: 0,
          ),
        ),
      ),
    );
    return PdfApi.saveDocument(
      name: 'invoice_${"${transaction.id}"}.pdf',
      pdf: pdf,
    );
  }

  static Widget buildInvoiceHeader(
    ImageProvider? imageProvider,
    Uint8List? imageData,
    SubscriptionTransactionModel transaction, {
    ProviderInfo? provider,
  }) {
    return pw.Padding(
      padding: const pw.EdgeInsets.symmetric(
        vertical: 1 * PdfPageFormat.cm,
        horizontal: 0.6 * PdfPageFormat.cm,
      ),
      child: Row(
        mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
        crossAxisAlignment: pw.CrossAxisAlignment.start,
        children: [
          pw.Expanded(
            child: Column(
              crossAxisAlignment: pw.CrossAxisAlignment.start,
              children: [
                Text(
                  'INVOICE',
                  style: TextStyle(fontSize: 24, fontWeight: FontWeight.normal),
                ),
                SizedBox(height: 0.2 * PdfPageFormat.cm),
                buildSimpleText(
                  title: "Transaction ID # ${transaction.id ?? ""}",
                  subtitle: "",
                ),
                SizedBox(height: 0.2 * PdfPageFormat.cm),
                buildSimpleText(
                  title:
                      "Date : ${DateConverter.isoStringToLocalDateOnly(transaction.createdAt!)}",
                  subtitle: "",
                ),
              ],
            ),
          ),
          SizedBox(width: 1 * PdfPageFormat.cm),

          pw.Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.end,
              mainAxisAlignment: MainAxisAlignment.start,
              children: [
                buildInvoiceImage(imageProvider, imageData),
                SizedBox(height: 0.2 * PdfPageFormat.cm),
                buildSimpleText(
                  title:
                      Get.find<SplashController>()
                          .configModel
                          .content
                          ?.businessAddress ??
                      "",
                  subtitle: "",
                  textAlign: TextAlign.end,
                ),
                SizedBox(height: 0.2 * PdfPageFormat.cm),
                buildSimpleText(
                  title:
                      Get.find<SplashController>()
                          .configModel
                          .content
                          ?.businessPhone ??
                      "",
                  subtitle: "",
                  textAlign: TextAlign.end,
                ),
                SizedBox(height: 0.2 * PdfPageFormat.cm),
                buildSimpleText(
                  title:
                      Get.find<SplashController>()
                          .configModel
                          .content
                          ?.businessEmail ??
                      "",
                  subtitle: "",
                  textAlign: TextAlign.end,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  static Widget buildInvoiceBody(
    SubscriptionTransactionModel transaction, {
    ProviderInfo? provider,
  }) {
    return Container(
      decoration: BoxDecoration(
        color: PdfColor.fromHex("FFFFFF"),
        border: pw.Border.all(color: PdfColor.fromHex("d7dae0")),
        borderRadius: pw.BorderRadius.circular(10),
      ),
      margin: const pw.EdgeInsets.symmetric(horizontal: 0.6 * PdfPageFormat.cm),
      padding: const pw.EdgeInsets.symmetric(vertical: 1 * PdfPageFormat.cm),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          pw.Padding(
            padding: const pw.EdgeInsets.symmetric(
              horizontal: 0.6 * PdfPageFormat.cm,
            ),
            child: Row(
              crossAxisAlignment: pw.CrossAxisAlignment.start,
              mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
              children: [
                buildTitleText(
                  title: "Provider",
                  subtitle: provider?.companyName ?? " ",
                ),
                SizedBox(width: 0.2 * PdfPageFormat.cm),
                buildTitleText(
                  title: "Phone",
                  subtitle: provider?.companyPhone ?? "",
                ),
                SizedBox(width: 0.2 * PdfPageFormat.cm),
                buildTitleText(
                  title: "Email",
                  subtitle: provider?.companyEmail ?? "",
                ),
                SizedBox(width: 0.2 * PdfPageFormat.cm),
                buildTitleText(
                  title:
                      "Invoice of (${transaction.packageLog?.payment?.currencyCode ?? ""})",
                  subtitle:
                      " ${Get.find<SplashController>().configModel.content?.currencySymbol ?? ""} ${transaction.credit ?? ""}",
                  crossAxisAliment: pw.CrossAxisAlignment.end,
                  style: TextStyle(
                    fontWeight: FontWeight.bold,
                    color: PdfColor.fromHex("0361a5"),
                    fontSize: Dimensions.fontSizeLarge,
                  ),
                ),
              ],
            ),
          ),

          pw.Container(
            height: 1,
            width: double.infinity,
            color: PdfColor.fromHex("d7dae0"),
            margin: const pw.EdgeInsets.symmetric(
              vertical: 0.6 * PdfPageFormat.cm,
            ),
          ),

          if (transaction.packageLog != null)
            pw.Padding(
              padding: const pw.EdgeInsets.symmetric(
                horizontal: 0.6 * PdfPageFormat.cm,
              ),
              child: Row(
                mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
                children: [
                  buildTitleText(
                    title: "Payment",
                    subtitle:
                        transaction.packageLog?.payment?.paymentMethod?.tr ??
                        "",
                  ),
                  pw.Container(
                    height: 40,
                    width: 0.5,
                    color: PdfColor.fromHex("d7dae0"),
                  ),
                  buildTitleText(
                    title: "Purchased",
                    subtitle: transaction.packageLog?.packageName ?? "",
                  ),
                  pw.Container(
                    height: 40,
                    width: 0.5,
                    color: PdfColor.fromHex("d7dae0"),
                  ),
                  buildTitleText(
                    title: "Duration",
                    subtitle:
                        "${DateConverter.countDays(dateTime: DateTime.tryParse(transaction.packageLog?.startDate ?? ""), endDate: DateTime.tryParse(transaction.packageLog?.endDate ?? ""))} days",
                  ),

                  pw.SizedBox(),
                  pw.SizedBox(),
                  pw.SizedBox(),
                  pw.SizedBox(),
                ],
              ),
            ),

          pw.SizedBox(height: 0.6 * PdfPageFormat.cm),

          buildInvoiceItem(transaction, provider: provider),

          pw.SizedBox(
            height: (12.5 - (Dimensions.invoiceImageHeight)) * PdfPageFormat.cm,
          ),
          buildSimpleText(
            title: "Thanks for using our service",
            subtitle: "",
            textAlign: TextAlign.center,
          ),
        ],
      ),
    );
  }

  static Widget buildInvoiceItem(
    SubscriptionTransactionModel transaction, {
    ProviderInfo? provider,
  }) {
    final headers = [
      'Transaction ID',
      pw.Container(height: 20, width: 0.5, color: PdfColor.fromHex("d7dae0")),
      (transaction.packageLog != null) ? 'Package Name' : "Payment Type",
      pw.Container(height: 20, width: 0.5, color: PdfColor.fromHex("d7dae0")),
      (transaction.packageLog != null) ? 'Expiry Date' : "Refunded To",
      pw.Container(height: 20, width: 0.5, color: PdfColor.fromHex("d7dae0")),
      if (transaction.packageLog != null) 'Amount',
      if (transaction.packageLog != null)
        pw.Container(height: 20, width: 0.5, color: PdfColor.fromHex("d7dae0")),
      if (transaction.packageLog != null) 'Vat',
      if (transaction.packageLog != null)
        pw.Container(height: 20, width: 0.5, color: PdfColor.fromHex("d7dae0")),
      'Total',
    ];
    final data = ["a"].map((item) {
      return [
        (transaction.id ?? ""),
        pw.Container(height: 15, width: 0.5, color: PdfColor.fromHex("d7dae0")),
        (transaction.packageLog != null)
            ? (transaction.packageLog?.packageName ?? "")
            : "Refunded",
        pw.Container(height: 15, width: 0.5, color: PdfColor.fromHex("d7dae0")),
        (transaction.packageLog != null)
            ? DateConverter.dateStringMonthYear(
                DateTime.tryParse(transaction.packageLog!.endDate ?? ""),
              )
            : "Receivable Balance",
        pw.Container(height: 15, width: 0.5, color: PdfColor.fromHex("d7dae0")),
        // if(transaction.packageLog !=null)"${DateConverter.countDays(
        //     dateTime: DateTime.tryParse(transaction.packageLog?.startDate ?? ""), endDate: DateTime.tryParse(transaction.packageLog?.endDate ?? "")
        // )} days",
        if (transaction.packageLog != null)
          "${(transaction.credit ?? 0) - (transaction.packageLog?.vatAmount ?? 0)}",
        if (transaction.packageLog != null)
          pw.Container(
            height: 15,
            width: 0.5,
            color: PdfColor.fromHex("d7dae0"),
          ),
        if (transaction.packageLog != null)
          "${(transaction.packageLog?.vatAmount ?? 0)}",
        if (transaction.packageLog != null)
          pw.Container(
            height: 15,
            width: 0.5,
            color: PdfColor.fromHex("d7dae0"),
          ),
        "${Get.find<SplashController>().configModel.content?.currencySymbol ?? ""} ${transaction.credit ?? ""}",
      ];
    }).toList();

    return pw.Padding(
      padding: const pw.EdgeInsets.symmetric(
        horizontal: 0.6 * PdfPageFormat.cm,
      ),
      child: TableHelper.fromTextArray(
        headers: headers,
        data: data,
        border: null,
        headerPadding: const EdgeInsets.symmetric(horizontal: 2),
        headerStyle: TextStyle(
          fontWeight: FontWeight.normal,
          color: PdfColor.fromHex("212b36"),
        ),
        rowDecoration: BoxDecoration(color: PdfColor.fromHex("fbfcfd")),
        headerDecoration: BoxDecoration(
          color: PdfColor.fromHex("f6f7f9"),
          borderRadius: const BorderRadius.only(
            topLeft: Radius.circular(10),
            topRight: Radius.circular(10),
          ),
        ),
        cellHeight: 50,
        cellStyle: TextStyle(
          fontWeight: FontWeight.normal,
          color: PdfColor.fromHex("334257"),
        ),
        columnWidths: {0: FixedColumnWidth(Get.width / 5)},
        cellAlignments: {
          0: Alignment.center,
          1: Alignment.center,
          2: Alignment.center,
          3: Alignment.center,
          4: Alignment.center,
          5: Alignment.center,
          6: Alignment.center,
          7: Alignment.center,
          8: Alignment.center,
          9: Alignment.center,
          10: Alignment.center,
        },
      ),
    );
  }

  static Widget buildFooter() => Container(
    decoration: BoxDecoration(color: PdfColor.fromHex("EDF4FA")),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.center,
      children: [
        Row(),
        SizedBox(height: 6 * PdfPageFormat.mm),
        buildSimpleText(
          subtitle:
              Get.find<SplashController>().configModel.content?.footerText ??
              "",
        ),
        SizedBox(height: 6 * PdfPageFormat.mm),
      ],
    ),
  );

  static pw.SpanningWidget buildSimpleText({
    String? title,
    String? subtitle,
    TextAlign textAlign = TextAlign.start,
  }) {
    final titleStyle = TextStyle(
      fontWeight: FontWeight.normal,
      color: PdfColor.fromHex("212b36"),
    );
    final subtitleStyle = TextStyle(
      fontWeight: FontWeight.normal,
      color: PdfColor.fromHex("6c7788"),
    );
    return title != null
        ? Text(
            title,
            style: titleStyle,
            maxLines: 2,
            overflow: TextOverflow.clip,
            textAlign: textAlign,
          )
        : subtitle != null
        ? Text(
            subtitle,
            style: subtitleStyle,
            overflow: TextOverflow.clip,
            textAlign: textAlign,
          )
        : pw.SizedBox();
  }

  static pw.ConstrainedBox buildTitleText({
    String? title,
    String? subtitle,
    TextStyle? style,
    CrossAxisAlignment crossAxisAliment = pw.CrossAxisAlignment.start,
  }) {
    final subtitleStyle =
        style ??
        TextStyle(
          fontWeight: FontWeight.normal,
          color: PdfColor.fromHex("212b36"),
        );
    final titleStyle = TextStyle(
      fontWeight: FontWeight.normal,
      color: PdfColor.fromHex("6c7788"),
    );
    return pw.ConstrainedBox(
      constraints: const pw.BoxConstraints(maxWidth: 5 * PdfPageFormat.cm),
      child: Column(
        crossAxisAlignment: crossAxisAliment,
        children: [
          Text(
            title ?? "",
            style: titleStyle,
            overflow: TextOverflow.clip,
            maxLines: 2,
          ),
          pw.SizedBox(height: 2 * PdfPageFormat.mm),
          Text(
            subtitle ?? "",
            style: subtitleStyle,
            overflow: TextOverflow.clip,
            maxLines: 2,
          ),
        ],
      ),
    );
  }

  static Widget buildInvoiceImage(
    ImageProvider? imageProvider,
    Uint8List? imageData,
  ) {
    return Container(
      width: Dimensions.invoiceImageWidth * PdfPageFormat.cm,
      height: Dimensions.invoiceImageHeight * PdfPageFormat.cm,
      child: imageProvider != null
          ? pw.Image(imageProvider)
          : imageData != null
          ? pw.Image(pw.MemoryImage(imageData), fit: pw.BoxFit.cover)
          : pw.SizedBox(),
    );
  }

  static Future<ImageProvider> downloadImage(String imageUrl) async {
    http.Response response = await http.get(Uri.parse(imageUrl));
    return MemoryImage(response.bodyBytes);
  }
}
