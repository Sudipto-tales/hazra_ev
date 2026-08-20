import 'package:flutter/material.dart';

import '../core/theme/app_colors.dart';

/// Network avatar with an initials fallback — mock data ships no images, so the
/// fallback is the path you will actually see.
class ProfileAvatar extends StatelessWidget {
  const ProfileAvatar({
    super.key,
    required this.initials,
    this.imageUrl,
    this.size = 44,
    this.borderColor,
    this.statusColor,
  });

  final String initials;
  final String? imageUrl;
  final double size;
  final Color? borderColor;

  /// When set, draws the small presence dot at the bottom-right.
  final Color? statusColor;

  @override
  Widget build(BuildContext context) {
    final Widget circle = Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: AppColors.primarySoft,
        border: borderColor == null
            ? null
            : Border.all(color: borderColor!, width: 2.5),
        image: (imageUrl != null && imageUrl!.isNotEmpty)
            ? DecorationImage(
                image: NetworkImage(imageUrl!),
                fit: BoxFit.cover,
              )
            : null,
      ),
      alignment: Alignment.center,
      child: (imageUrl == null || imageUrl!.isEmpty)
          ? Text(
              initials,
              style: TextStyle(
                color: AppColors.primaryDark,
                fontWeight: FontWeight.w700,
                fontSize: size * 0.36,
              ),
            )
          : null,
    );

    if (statusColor == null) return circle;

    return SizedBox(
      width: size,
      height: size,
      child: Stack(
        clipBehavior: Clip.none,
        children: <Widget>[
          circle,
          Positioned(
            right: -1,
            bottom: -1,
            child: Container(
              width: size * 0.28,
              height: size * 0.28,
              decoration: BoxDecoration(
                color: statusColor,
                shape: BoxShape.circle,
                border: Border.all(
                  color: Theme.of(context).scaffoldBackgroundColor,
                  width: 2,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
