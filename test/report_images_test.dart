import 'package:employeetracking_mobile_app/core/config/api_config.dart';
import 'package:employeetracking_mobile_app/data/api/wire.dart';
import 'package:employeetracking_mobile_app/data/models/models.dart';
import 'package:employeetracking_mobile_app/features/reports/widgets/image_tile.dart';
import 'package:flutter_test/flutter_test.dart';

/// Report attachments and sale-line artwork, on the client side.
///
/// The decode is where the important distinction lives: the server sends
/// `images` only under `?include=images`, so **absent** ("you did not ask")
/// and **empty** ("this report has no photos") have to stay apart. Collapsing
/// them is what left the detail pages rendering empty slots.

/// Marks "do not put an `images` key in the payload at all", which is a
/// different thing from putting `null` there.
const Object _omitted = Object();

Map<String, dynamic> _reportJson({
  Object? images = _omitted,
  List<Map<String, dynamic>> sales = const <Map<String, dynamic>>[],
  int imageCount = 0,
}) {
  final Map<String, dynamic> j = <String, dynamic>{
    'id': 'rep-1',
    'companyName': 'Bardhaman Motors',
    'branchName': null,
    'companyId': null,
    'branchId': null,
    'visitId': null,
    'sessionId': 'ses-1',
    'title': 'Demo + two units',
    'body': 'Went well.',
    'imageCount': imageCount,
    'submittedAt': '2026-11-05T08:00:00Z',
    'latitude': 23.24,
    'longitude': 87.86,
    'status': 'submitted',
    'dealValue': null,
    'followUpOn': null,
    'sales': sales,
    'paymentReceived': null,
  };
  if (!identical(images, _omitted)) {
    j['images'] = images;
  }
  return j;
}

Map<String, dynamic> _imageJson(String id, int position) => <String, dynamic>{
      'id': id,
      'reportId': 'rep-1',
      'url': 'storage/uploads/reports/rep-1/$id.jpg',
      'thumbnailUrl': null,
      'width': 1024,
      'height': 768,
      'bytes': 4096,
      'capturedAt': null,
      'latitude': null,
      'longitude': null,
      'uploadState': 'uploaded',
      'position': position,
    };

void main() {
  group('absent vs empty', () {
    test('no images key at all means "not fetched"', () {
      final VisitReport r = Wire.report(_reportJson(imageCount: 3));

      expect(r.images, isNull);
      expect(r.hasFetchedImages, isFalse);
      // The UI still knows there are three, and says so honestly rather than
      // rendering an empty grid.
      expect(r.imageCount, 3);
      expect(r.visibleImageCount, 3);
    });

    test('an empty array means "fetched, and there are none"', () {
      final VisitReport r =
          Wire.report(_reportJson(images: <Map<String, dynamic>>[]));

      expect(r.images, isEmpty);
      expect(r.hasFetchedImages, isTrue);
      expect(r.visibleImageCount, 0);
    });

    test('a populated array is used in preference to the count', () {
      // The two can disagree for a moment when an upload lands between the
      // list read and the detail read. The array is the one that can actually
      // be rendered, so it wins.
      final VisitReport r = Wire.report(_reportJson(
        imageCount: 5,
        images: <Map<String, dynamic>>[_imageJson('a', 0)],
      ));

      expect(r.imageCount, 5);
      expect(r.visibleImageCount, 1,
          reason: 'never draw more slots than there are URLs to fill them');
    });
  });

  group('decoding an attachment', () {
    test('carries every field Present::image sends', () {
      final VisitReport r = Wire.report(_reportJson(
        imageCount: 2,
        images: <Map<String, dynamic>>[_imageJson('a', 0), _imageJson('b', 1)],
      ));

      expect(r.images, hasLength(2));
      final ReportImage first = r.images!.first;
      expect(first.id, 'a');
      expect(first.reportId, 'rep-1');
      expect(first.url, 'storage/uploads/reports/rep-1/a.jpg');
      expect(first.width, 1024);
      expect(first.height, 768);
      expect(first.bytes, 4096);
      expect(first.thumbnailUrl, isNull);
      expect(first.capturedAt, isNull);
    });

    test('aspectRatio is null unless both dimensions are usable', () {
      const ReportImage sized = ReportImage(
        id: 'a',
        reportId: 'r',
        url: 'u',
        width: 1024,
        height: 768,
      );
      expect(sized.aspectRatio, closeTo(1024 / 768, 1e-9));

      // Reserving a box from a zero or missing dimension would divide by zero
      // or lay out a 0-height cell.
      for (final ReportImage bad in <ReportImage>[
        const ReportImage(id: 'a', reportId: 'r', url: 'u'),
        const ReportImage(id: 'a', reportId: 'r', url: 'u', width: 100),
        const ReportImage(
            id: 'a', reportId: 'r', url: 'u', width: 100, height: 0),
      ]) {
        expect(bad.aspectRatio, isNull);
      }
    });
  });

  group('URL resolution', () {
    test('a document-root path resolves against the origin, not /api/v1', () {
      final String? url =
          ReportImageTile.resolve('storage/uploads/reports/rep-1/a.jpg');

      expect(url, '${ApiConfig.originUrl}/storage/uploads/reports/rep-1/a.jpg');
      // The bug this guards: resolving against baseUrl would produce
      // `.../api/v1/storage/...`, a 404 that reads as a missing file.
      expect(url, isNot(contains('/api/v1/storage')));
    });

    test('a leading slash is not doubled', () {
      expect(
        ReportImageTile.resolve('/storage/a.jpg'),
        '${ApiConfig.originUrl}/storage/a.jpg',
      );
    });

    test('an absolute URL is left alone', () {
      expect(
        ReportImageTile.resolve('https://cdn.example.com/a.jpg'),
        'https://cdn.example.com/a.jpg',
      );
    });

    test('null and blank resolve to null, never to a bare origin', () {
      // Returning the origin for a blank path would request the site root and
      // render whatever HTML came back as a broken image.
      expect(ReportImageTile.resolve(null), isNull);
      expect(ReportImageTile.resolve(''), isNull);
      expect(ReportImageTile.resolve('   '), isNull);
    });
  });

  group('sale line artwork', () {
    test('imageUrl decodes when the server sends it', () {
      final VisitReport r = Wire.report(_reportJson(
        sales: <Map<String, dynamic>>[
          <String, dynamic>{
            'productId': 'prod',
            'productName': 'City Rider',
            'category': 'scooty',
            'colorName': 'Crimson',
            'colorArgb': 0xFFCC0000,
            'units': 2,
            'imageUrl': 'storage/catalogue/prod/red-1.jpg',
          },
        ],
      ));

      expect(r.sales.single.imageUrl, 'storage/catalogue/prod/red-1.jpg');
      expect(r.sales.single.units, 2);
    });

    test('a server without the field degrades to null, not to a crash', () {
      final VisitReport r = Wire.report(_reportJson(
        sales: <Map<String, dynamic>>[
          <String, dynamic>{
            'productId': 'prod',
            'productName': 'City Rider',
            'category': 'scooty',
            'colorName': 'Crimson',
            'colorArgb': 0xFFCC0000,
            'units': 1,
          },
        ],
      ));

      // Null is the signal to fall back to the painted artwork, which is why
      // it must not throw or coerce to an empty string.
      expect(r.sales.single.imageUrl, isNull);
    });

    test('copyWith keeps the artwork', () {
      const ProductSaleLine line = ProductSaleLine(
        productId: 'prod',
        productName: 'City Rider',
        category: ProductCategory.scooty,
        colorName: 'Crimson',
        colorArgb: 0xFFCC0000,
        units: 1,
        imageUrl: 'storage/catalogue/prod/red-1.jpg',
      );

      // The editor calls copyWith to change units/colour; dropping the image
      // there would blank the photo on every edit.
      expect(line.copyWith(units: 4).imageUrl, line.imageUrl);
      expect(line.copyWith(colorName: 'Indigo').imageUrl, line.imageUrl);
    });
  });
}
