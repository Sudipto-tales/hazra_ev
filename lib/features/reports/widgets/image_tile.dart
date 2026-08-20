import 'package:flutter/material.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/dimens.dart';

/// Placeholder attachment tile.
///
/// No images ship with the static dataset, so this renders a deterministic
/// tinted placeholder. Replace the body with `Image.file` / `Image.network`
/// once the picker and upload service are wired up.
class ReportImageTile extends StatelessWidget {
  const ReportImageTile({
    super.key,
    required this.index,
    this.size = 88,
    this.onRemove,
  });

  final int index;
  final double size;
  final VoidCallback? onRemove;

  static const List<Color> _tints = <Color>[
    Color(0xFFDBE7FF),
    Color(0xFFE3F5E8),
    Color(0xFFFDEBDC),
    Color(0xFFEAE4FB),
    Color(0xFFFCE4EC),
  ];

  @override
  Widget build(BuildContext context) {
    final Color tint = _tints[index % _tints.length];

    return SizedBox(
      width: size,
      height: size,
      child: Stack(
        children: <Widget>[
          Container(
            width: size,
            height: size,
            decoration: BoxDecoration(
              color: tint,
              borderRadius: BorderRadius.circular(Radii.md),
              border: Border.all(color: Colors.black.withValues(alpha: 0.05)),
            ),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: <Widget>[
                Icon(
                  Icons.photo_camera_back_outlined,
                  size: size * 0.28,
                  color: AppColors.textSecondary,
                ),
                const SizedBox(height: 4),
                Text(
                  'IMG_${(index + 1).toString().padLeft(2, '0')}',
                  style: const TextStyle(
                    fontSize: 9.5,
                    fontWeight: FontWeight.w600,
                    color: AppColors.textSecondary,
                  ),
                ),
              ],
            ),
          ),
          if (onRemove != null)
            Positioned(
              top: 2,
              right: 2,
              child: InkWell(
                onTap: onRemove,
                borderRadius: BorderRadius.circular(Radii.pill),
                child: Container(
                  padding: const EdgeInsets.all(3),
                  decoration: const BoxDecoration(
                    color: Colors.black54,
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(
                    Icons.close_rounded,
                    size: 13,
                    color: Colors.white,
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}
