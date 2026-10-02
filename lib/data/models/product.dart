/// EV catalogue. What a seller can log against a visit report.
///
/// No widget imports — colours travel as ARGB ints so the data layer stays
/// pure and can be served by JSON later without a Flutter dependency.
///
/// **There is deliberately no price field.** Field sellers log units and the
/// payment they actually received; pricing is not shown on this surface.
library;

enum ProductCategory { scooty, bike, bicycle, others }

extension ProductCategoryX on ProductCategory {
  String get label => switch (this) {
        ProductCategory.scooty => 'Scooty',
        ProductCategory.bike => 'Bike',
        ProductCategory.bicycle => 'E-Bicycle',
        ProductCategory.others => 'Others',
      };

  /// Wire value — what the backend would send/accept.
  String get code => name;

  static ProductCategory fromCode(String code) =>
      ProductCategory.values.firstWhere(
        (ProductCategory c) => c.name == code,
        orElse: () => ProductCategory.others,
      );
}

/// One colourway of a product. The gallery is **per colour** — whoever uploads
/// the catalogue uploads a separate image set for every colour, so picking a
/// colour swaps the whole set rather than tinting one photo.
class ProductColor {
  const ProductColor({
    required this.name,
    required this.argb,
    this.imageUrls = const <String>[],
    this.inStock = true,
    this.id,
    this.position = 0,
  });

  final String? id;
  final int position;
  final String name;

  /// 0xAARRGGBB. Rendered with `Color(argb)` in the UI layer.
  final int argb;

  /// Ordered remote gallery. The first photo is the primary image.
  final List<String> imageUrls;

  final bool inStock;

  /// How many frames the gallery has for this colour, placeholder or real.
  int get imageCount => imageUrls.isEmpty ? 3 : imageUrls.length;
}

class Product {
  const Product({
    required this.id,
    required this.category,
    required this.brand,
    required this.name,
    required this.modelCode,
    required this.rating,
    required this.warrantyYears,
    required this.warrantyNote,
    required this.rangeKm,
    required this.topSpeedKmph,
    required this.chargingTime,
    required this.batteryCapacity,
    required this.motorPower,
    required this.loadCapacityKg,
    required this.colors,
    this.listedAt,
    this.highlights = const <String>[],
    this.slug = '',
    this.heroImage = '',
    this.isFeatured = false,
    this.featuredOrder = 0,
    this.active = true,
    this.defaultColorId,
    this.pageContent = const <String, dynamic>{},
    this.featureCards = const <ProductFeatureCard>[],
  });

  final String slug;
  final String heroImage;
  final bool isFeatured;
  final int featuredOrder;
  final bool active;
  final String? defaultColorId;
  final Map<String, dynamic> pageContent;
  final List<ProductFeatureCard> featureCards;
  final String id;
  final ProductCategory category;
  final String brand;
  final String name;
  final String modelCode;

  /// 0–5.
  final double rating;

  final int warrantyYears;

  /// e.g. "3 yrs vehicle + 4 yrs battery".
  final String warrantyNote;

  /// Mileage on a full charge.
  final int rangeKm;
  final int topSpeedKmph;
  final String chargingTime;
  final String batteryCapacity;
  final String motorPower;
  final int loadCapacityKg;

  final List<ProductColor> colors;
  final List<String> highlights;

  /// When the admin listed it. Null for the products that shipped with the
  /// base catalogue — nobody listed those, so they are never "new".
  ///
  /// (`DateTime` has no const constructor, so this being nullable is also what
  /// lets the seed catalogue stay a `const` list.)
  final DateTime? listedAt;

  /// Listed within the last week — worth flagging in the report form's picker.
  bool get isNew =>
      listedAt != null &&
      DateTime.now().difference(listedAt!) < const Duration(days: 7);

  String get displayName => '$brand $name';

  ProductColor colorByName(String? name) {
    if (name != null) {
      for (final ProductColor c in colors) {
        if (c.name == name) return c;
      }
    }
    return colors.first;
  }

  /// The spec sheet shown under the gallery. Label/value only so the sheet can
  /// render an unknown-length table without knowing the field names.
  List<({String label, String value})> get specSheet =>
      <({String label, String value})>[
        (label: 'Warranty', value: warrantyNote),
        (label: 'Mileage / range', value: '$rangeKm km per charge'),
        (label: 'Top speed', value: '$topSpeedKmph km/h'),
        (label: 'Charging time', value: chargingTime),
        (label: 'Battery', value: batteryCapacity),
        (label: 'Motor', value: motorPower),
        (label: 'Load capacity', value: '$loadCapacityKg kg'),
        (label: 'Model code', value: modelCode),
      ];
}

/// Write-side payload for the admin's add / edit product screen. A null [id]
/// means create.
///
/// Mirrors [Product] minus the derived members — and, like [Product], carries
/// **no price**.
class ProductDraft {
  const ProductDraft({
    required this.category,
    required this.brand,
    required this.name,
    required this.modelCode,
    required this.rating,
    required this.warrantyYears,
    required this.warrantyNote,
    required this.rangeKm,
    required this.topSpeedKmph,
    required this.chargingTime,
    required this.batteryCapacity,
    required this.motorPower,
    required this.loadCapacityKg,
    required this.colors,
    this.id,
    this.highlights = const <String>[],
    this.slug = '',
    this.heroImage = '',
    this.isFeatured = false,
    this.featuredOrder = 0,
    this.active,
    this.defaultColorId,
    this.pageContent = const <String, dynamic>{},
    this.featureCards = const <ProductFeatureCard>[],
  });

  factory ProductDraft.from(Product p) => ProductDraft(
        id: p.id,
        category: p.category,
        brand: p.brand,
        name: p.name,
        modelCode: p.modelCode,
        rating: p.rating,
        warrantyYears: p.warrantyYears,
        warrantyNote: p.warrantyNote,
        rangeKm: p.rangeKm,
        topSpeedKmph: p.topSpeedKmph,
        chargingTime: p.chargingTime,
        batteryCapacity: p.batteryCapacity,
        motorPower: p.motorPower,
        loadCapacityKg: p.loadCapacityKg,
        colors: p.colors,
        highlights: p.highlights,
        slug: p.slug,
        heroImage: p.heroImage,
        isFeatured: p.isFeatured,
        featuredOrder: p.featuredOrder,
        active: p.active,
        defaultColorId: p.defaultColorId,
        pageContent: p.pageContent,
        featureCards: p.featureCards,
      );

  final String slug;
  final String heroImage;
  final bool isFeatured;
  final int featuredOrder;

  /// Null leaves publication unchanged on PATCH; creation defaults to published.
  final bool? active;
  final String? defaultColorId;
  final Map<String, dynamic> pageContent;
  final List<ProductFeatureCard> featureCards;
  final String? id;
  final ProductCategory category;
  final String brand;
  final String name;
  final String modelCode;
  final double rating;
  final int warrantyYears;
  final String warrantyNote;
  final int rangeKm;
  final int topSpeedKmph;
  final String chargingTime;
  final String batteryCapacity;
  final String motorPower;
  final int loadCapacityKg;
  final List<ProductColor> colors;
  final List<String> highlights;

  bool get isCreate => id == null;
}

/// A website detail card, optionally shown for just one colour.
class ProductFeatureCard {
  const ProductFeatureCard({
    required this.id,
    required this.title,
    this.description = '',
    this.image = '',
    this.alt = '',
    this.colorId = '',
    this.visible = true,
    this.legacyCrop = false,
  });

  final String id;
  final String title;
  final String description;
  final String image;
  final String alt;
  final String colorId;
  final bool visible;
  final bool legacyCrop;

  ProductFeatureCard shared() => ProductFeatureCard(
        id: id,
        title: title,
        description: description,
        image: image,
        alt: alt,
        visible: visible,
        legacyCrop: legacyCrop,
      );
}

/// One line of the sale logged on a report: which product, in which colour,
/// how many units.
class ProductSaleLine {
  const ProductSaleLine({
    required this.productId,
    required this.productName,
    required this.category,
    required this.colorName,
    required this.colorArgb,
    required this.units,
  });

  final String productId;

  /// Denormalised so a report still reads correctly if the catalogue changes.
  final String productName;
  final ProductCategory category;
  final String colorName;
  final int colorArgb;
  final int units;

  ProductSaleLine copyWith({String? colorName, int? colorArgb, int? units}) =>
      ProductSaleLine(
        productId: productId,
        productName: productName,
        category: category,
        colorName: colorName ?? this.colorName,
        colorArgb: colorArgb ?? this.colorArgb,
        units: units ?? this.units,
      );
}
