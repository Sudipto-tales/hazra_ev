/// Spacing / radius scale. Use these instead of magic numbers.
class Insets {
  const Insets._();
  static const double xs = 4;
  static const double sm = 8;
  static const double md = 12;
  static const double lg = 16;
  static const double xl = 20;
  static const double xxl = 24;
  static const double xxxl = 32;
}

class Radii {
  const Radii._();
  static const double sm = 8;
  static const double md = 12;
  static const double lg = 16;
  static const double xl = 22;
  static const double pill = 999;
}

class Sizes {
  const Sizes._();

  /// Minimum tappable height — large touch targets are a design requirement.
  static const double touchTarget = 48;
  static const double primaryActionHeight = 56;
  static const double avatarSm = 36;
  static const double avatarMd = 48;
  static const double avatarLg = 88;
  static const double bannerHeight = 132;
}
