import 'package:employeetracking_mobile_app/data/mock/mock_data.dart';
import 'package:employeetracking_mobile_app/data/mock/product_catalog.dart';
import 'package:employeetracking_mobile_app/data/models/models.dart';
import 'package:employeetracking_mobile_app/data/repositories/employee_repository.dart';
import 'package:employeetracking_mobile_app/data/repositories/mock_employee_repository.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  MockEmployeeRepository repo() =>
      MockEmployeeRepository(latency: Duration.zero);

  group('catalogue', () {
    test('every category has products', () {
      for (final ProductCategory c in ProductCategory.values) {
        expect(
          ProductCatalog.byCategory(c),
          isNotEmpty,
          reason: 'category ${c.label} would render an empty picker',
        );
      }
    });

    test('every product has at least one colour with an image set', () {
      for (final Product p in ProductCatalog.all) {
        expect(p.colors, isNotEmpty, reason: p.id);
        for (final ProductColor c in p.colors) {
          expect(c.imageCount, greaterThan(0), reason: '${p.id}/${c.name}');
        }
      }
    });

    test('at least one colour of every product is in stock', () {
      for (final Product p in ProductCatalog.all) {
        expect(
          p.colors.any((ProductColor c) => c.inStock),
          isTrue,
          reason: '${p.id} could not be selected at all',
        );
      }
    });

    test('the spec sheet never leaks a price', () {
      for (final Product p in ProductCatalog.all) {
        for (final ({String label, String value}) s in p.specSheet) {
          final String text = '${s.label} ${s.value}'.toLowerCase();
          expect(text.contains('price'), isFalse, reason: p.id);
          expect(text.contains('bdt'), isFalse, reason: p.id);
          expect(text.contains('₹'), isFalse, reason: p.id);
        }
      }
    });

    test('colorByName falls back instead of throwing', () {
      final Product p = ProductCatalog.all.first;
      expect(p.colorByName('No Such Colour').name, p.colors.first.name);
      expect(p.colorByName(null).name, p.colors.first.name);
    });

    test('products() filters by category', () async {
      final List<Product> bikes =
          await repo().products(category: ProductCategory.bike);
      expect(bikes, isNotEmpty);
      expect(
        bikes.every((Product p) => p.category == ProductCategory.bike),
        isTrue,
      );
    });

    test('products() searches name and model code', () async {
      final List<Product> hits = await repo().products(query: 'chalo');
      expect(hits, isNotEmpty);
      expect(hits.first.brand.toLowerCase(), contains('chalo'));
    });
  });

  group('submitting a report', () {
    test('keeps the typed company and branch with no id behind them', () async {
      final MockEmployeeRepository r = repo();
      final VisitReport saved = await r.submitReport(
        const ReportDraft(
          companyName: 'Mirpur Motors (walk-in)',
          branchName: 'Mirpur 10',
          title: 'Walk-in dealer visit',
          body: 'Unlisted shop, wants to stock high-speed scooters.',
        ),
      );

      expect(saved.companyName, 'Mirpur Motors (walk-in)');
      expect(saved.branchName, 'Mirpur 10');
      expect(saved.companyId, isNull);
      expect(saved.branchId, isNull);
      expect(saved.hasSale, isFalse);

      final List<VisitReport> all = await r.reports();
      expect(all.first.id, saved.id);
    });

    test('carries the sale lines, unit total and payment received', () async {
      final Product p = ProductCatalog.byCategory(ProductCategory.scooty).first;
      final ProductColor c = p.colors.first;

      final VisitReport saved = await repo().submitReport(
        ReportDraft(
          companyName: 'Banani EV Point',
          branchName: null,
          title: 'Three units delivered',
          body: 'Handed over three units and collected a part payment.',
          sales: <ProductSaleLine>[
            ProductSaleLine(
              productId: p.id,
              productName: p.displayName,
              category: p.category,
              colorName: c.name,
              colorArgb: c.argb,
              units: 3,
            ),
          ],
          paymentReceived: 'BDT 90,000',
        ),
      );

      expect(saved.hasSale, isTrue);
      expect(saved.unitsSold, 3);
      expect(saved.sales.single.colorName, c.name);
      expect(saved.paymentReceived, 'BDT 90,000');
    });

    test('a linked visit keeps its ids so the map can still place it', () async {
      final VisitReport saved = await repo().submitReport(
        const ReportDraft(
          companyName: 'ABC Trading Ltd.',
          branchName: 'Gulshan Head Office',
          companyId: 'co_abc',
          branchId: 'br_abc_hq',
          visitId: 'vis_1',
          title: 'Stock review',
          body: 'Reviewed shelf movement and placed a reorder.',
        ),
      );

      expect(saved.companyId, 'co_abc');
      expect(saved.visitId, 'vis_1');
    });

    test('files the report with no images, then attaches them', () async {
      final MockEmployeeRepository r = repo();

      const ReportAttachment shot = ReportAttachment(
        path: '/tmp/hazra-ev-test/shopfront.jpg',
        name: 'shopfront.jpg',
        byteSize: 512 * 1024,
      );

      final VisitReport saved = await r.submitReport(
        const ReportDraft(
          companyName: 'Uttara EV Hub',
          branchName: null,
          title: 'Shopfront photos',
          body: 'Photographed the display and the stock room.',
          images: <ReportAttachment>[shot],
        ),
      );

      // POST /reports carries text only — the pictures are a second call, and
      // a report that never reaches that call has none.
      expect(saved.imageCount, 0);

      final List<ReportImage> stored =
          await r.uploadReportImages(saved.id, <ReportAttachment>[shot]);

      expect(stored, hasLength(1));
      expect(stored.single.reportId, saved.id);
      expect((await r.reportById(saved.id)).imageCount, 1);
    });

    test('an attachment the server would refuse never counts as uploaded',
        () async {
      final MockEmployeeRepository r = repo();

      final VisitReport saved = await r.submitReport(
        const ReportDraft(
          companyName: 'Savar Motors',
          branchName: null,
          title: 'Oversized attachment',
          body: 'The camera produced a frame larger than the server accepts.',
        ),
      );

      await expectLater(
        r.uploadReportImages(saved.id, <ReportAttachment>[
          const ReportAttachment(
            path: '/tmp/hazra-ev-test/huge.png',
            name: 'huge.png',
            byteSize: ReportAttachment.maxBytes + 1,
          ),
        ]),
        throwsStateError,
      );

      expect((await r.reportById(saved.id)).imageCount, 0);
    });
  });

  group('fixtures', () {
    test('the walk-in fixture has a name but no company id', () {
      final VisitReport walkIn = MockData.todayReports
          .firstWhere((VisitReport r) => r.id == 'rep_5');
      expect(walkIn.companyId, isNull);
      expect(walkIn.branchId, isNull);
      expect(walkIn.companyName, isNotEmpty);
      expect(walkIn.hasSale, isTrue);
    });

    test('every fixture report carries a company name', () {
      for (final VisitReport r in MockData.allReports) {
        expect(r.companyName.trim(), isNotEmpty, reason: r.id);
      }
    });

    test('a sale line never claims a colour the product does not offer', () {
      for (final VisitReport r in MockData.allReports) {
        for (final ProductSaleLine l in r.sales) {
          final Product? p = ProductCatalog.byId(l.productId);
          expect(p, isNotNull, reason: l.productId);
          expect(
            p!.colors.any((ProductColor c) => c.name == l.colorName),
            isTrue,
            reason: '${r.id}: ${l.productName} / ${l.colorName}',
          );
        }
      }
    });
  });
}
