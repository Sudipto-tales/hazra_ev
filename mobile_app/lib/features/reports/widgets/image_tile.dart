import 'dart:io';

import 'package:flutter/material.dart';

import '../../../core/config/api_config.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/dimens.dart';
import '../../../core/theme/theme_ext.dart';
import '../../../data/models/models.dart';
import '../../../data/repositories/employee_repository.dart';

/// One attachment square, in one of three states.
///
/// * [ReportImageTile.network] — **a stored photograph.** `GET /reports/{id}`
///   now serves the URL array under `?include=images`, so this is the normal
///   case and the one the detail screens use.
/// * [ReportImageTile.file] — a picture picked on this device and not yet (or
///   only just) uploaded. Rendered from disk, because that is where it is.
/// * [ReportImageTile.new] — no picture available. Kept for the mock/demo
///   build, which has no stored files, and for a server too old to serve the
///   array. Renders an obviously-empty numbered slot.
///
/// A deterministic tinted rectangle is still deliberately *not* one of the
/// states. At a glance it was indistinguishable from a real thumbnail, which
/// made a missing-data problem look like a solved one.
class ReportImageTile extends StatelessWidget {
  /// The honest placeholder: the app knows a picture exists and cannot show it.
  const ReportImageTile({
    super.key,
    required this.index,
    this.size = 88,
    this.onRemove,
  })  : attachment = null,
        image = null;

  /// A picture on this device's storage.
  const ReportImageTile.file({
    super.key,
    required ReportAttachment this.attachment,
    this.size = 88,
    this.onRemove,
  })  : index = 0,
        image = null;

  /// A stored photograph on the server.
  const ReportImageTile.network({
    super.key,
    required ReportImage this.image,
    required this.index,
    this.size = 88,
    this.onRemove,
  }) : attachment = null;

  /// Position in the attachment list — the only thing the placeholder can
  /// honestly say about a picture it cannot load.
  final int index;

  /// Set by [ReportImageTile.file] only.
  final ReportAttachment? attachment;

  /// Set by [ReportImageTile.network] only.
  final ReportImage? image;

  final double size;
  final VoidCallback? onRemove;

  /// Absolute URL for a stored attachment.
  ///
  /// `url` is relative to the PHP **document root**, not the `/api/v1` prefix
  /// (`storage/uploads/reports/...`), which is exactly what
  /// [ApiConfig.originUrl] exists for. Getting this wrong yields a 404 that
  /// looks like a missing file rather than a wrong path, so it is resolved in
  /// one place.
  static String? resolve(String? raw) {
    if (raw == null) return null;
    final String u = raw.trim();
    if (u.isEmpty) return null;
    if (u.startsWith('http://') || u.startsWith('https://')) return u;
    final String base = ApiConfig.originUrl;
    return u.startsWith('/') ? '$base$u' : '$base/$u';
  }

  Widget _body(BuildContext context) {
    final ReportImage? stored = image;
    if (stored != null) {
      // Prefer a server thumbnail when one exists; nothing generates them
      // today, so in practice this loads the full image and lets the image
      // cache downscale it to the tile.
      final String? url = resolve(stored.thumbnailUrl) ?? resolve(stored.url);
      if (url == null) return _Placeholder(index: index, size: size);

      return Image.network(
        url,
        width: size,
        height: size,
        fit: BoxFit.cover,
        // Decode at roughly the size actually drawn rather than full
        // resolution: an 8 MB phone photo in an 88 px tile is otherwise
        // decoded at full size and a grid of them will trip the image cache.
        cacheWidth: (size * MediaQuery.devicePixelRatioOf(context)).round(),
        loadingBuilder: (BuildContext context, Widget child,
                ImageChunkEvent? progress) =>
            progress == null ? child : _Loading(size: size),
        // A stored row with an unreachable file is a real state — the upload
        // directory can be pruned, or the origin can be wrong. Say so instead
        // of showing a broken-image glyph with an exception behind it.
        errorBuilder: (_, __, ___) =>
            _Placeholder(index: index, size: size, label: 'Unavailable'),
      );
    }

    final ReportAttachment? local = attachment;
    if (local != null) {
      return Image.file(
        File(local.path),
        width: size,
        height: size,
        fit: BoxFit.cover,
        // The camera cache is not ours; the file can be gone by the time this
        // rebuilds. Say so rather than throwing a grey box with an exception
        // behind it.
        errorBuilder: (_, __, ___) =>
            _Placeholder(index: index, size: size, label: 'Missing'),
      );
    }

    return _Placeholder(index: index, size: size);
  }

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
              child: _body(context),
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

/// While the bytes are in flight. Deliberately a plain surface rather than a
/// spinner per tile: a grid of spinners reads as broken.
class _Loading extends StatelessWidget {
  const _Loading({required this.size});

  final double size;

  @override
  Widget build(BuildContext context) => Container(
        width: size,
        height: size,
        color: context.isDark ? AppColors.surfaceAltDark : AppColors.surfaceAlt,
        child: Center(
          child: SizedBox(
            width: size * 0.2,
            height: size * 0.2,
            child: CircularProgressIndicator(
              strokeWidth: 2,
              color: AppColors.textSecondary.withValues(alpha: 0.5),
            ),
          ),
        ),
      );
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
