import 'dart:io';

import 'package:flutter/material.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/dimens.dart';
import '../../../core/theme/theme_ext.dart';
import '../../../data/repositories/employee_repository.dart';

/// One attachment square, in one of two genuinely different states.
///
/// * [ReportImageTile.file] — a picture picked on this device and not yet (or
///   only just) uploaded. Rendered from disk, because that is where it is.
/// * [ReportImageTile.new] — **no picture available.** `GET /reports` and
///   `GET /reports/{id}` return `imageCount` and nothing else: `Present::report`
///   carries no images array and there is no `include=images`, so a report read
///   back from the server knows how many photos it has and cannot fetch one.
///   This state renders an obviously-empty numbered slot.
///
/// The old behaviour — a deterministic tinted rectangle — is deliberately gone.
/// At a glance it was indistinguishable from a real thumbnail, which made a
/// missing-data problem look like a solved one on both this screen and the
/// admin's.
class ReportImageTile extends StatelessWidget {
  /// The honest placeholder: the app knows a picture exists and cannot show it.
  const ReportImageTile({
    super.key,
    required this.index,
    this.size = 88,
    this.onRemove,
  }) : attachment = null;

  /// A picture on this device's storage.
  const ReportImageTile.file({
    super.key,
    required ReportAttachment this.attachment,
    this.size = 88,
    this.onRemove,
  }) : index = 0;

  /// Position in the attachment list — the only thing the placeholder can
  /// honestly say about a picture it cannot load.
  final int index;

  /// Set by [ReportImageTile.file] only.
  final ReportAttachment? attachment;

  final double size;
  final VoidCallback? onRemove;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: size,
      height: size,
      child: Stack(
        children: <Widget>[
          ClipRRect(
            borderRadius: BorderRadius.circular(Radii.md),
            child: SizedBox(
              width: size,
              height: size,
              child: attachment == null
                  ? _Placeholder(index: index, size: size)
                  : Image.file(
                      File(attachment!.path),
                      width: size,
                      height: size,
                      fit: BoxFit.cover,
                      // The camera cache is not ours; the file can be gone by
                      // the time this rebuilds. Say so rather than throwing a
                      // grey box with an exception behind it.
                      errorBuilder: (_, __, ___) => _Placeholder(
                        index: index,
                        size: size,
                        label: 'Missing',
                      ),
                    ),
            ),
          ),
          Positioned.fill(
            child: IgnorePointer(
              child: DecoratedBox(
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(Radii.md),
                  border: Border.all(
                    color: Colors.black.withValues(alpha: 0.08),
                  ),
                ),
              ),
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

/// A slot, not a photo. Neutral surface colour and a struck-through image icon,
/// so it never reads as content.
class _Placeholder extends StatelessWidget {
  const _Placeholder({required this.index, required this.size, this.label});

  final int index;
  final double size;
  final String? label;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      color: context.isDark ? AppColors.surfaceAltDark : AppColors.surfaceAlt,
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: <Widget>[
          Icon(
            Icons.image_not_supported_outlined,
            size: size * 0.26,
            color: AppColors.textSecondary,
          ),
          const SizedBox(height: 4),
          Text(
            label ?? 'Photo ${index + 1}',
            style: const TextStyle(
              fontSize: 9.5,
              fontWeight: FontWeight.w600,
              color: AppColors.textSecondary,
            ),
          ),
        ],
      ),
    );
  }
}
