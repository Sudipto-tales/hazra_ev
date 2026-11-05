import 'package:flutter/material.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/dimens.dart';
import '../../../core/theme/theme_ext.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/models/models.dart';
import 'image_tile.dart';

/// The attachments block on a report detail screen.
///
/// Renders from [VisitReport.images] when the array was fetched, and falls back
/// to [VisitReport.imageCount] placeholders when it was not — the mock build,
/// or a server older than `?include=images`. Those are genuinely different
/// states and the widget keeps them apart rather than showing an empty grid for
/// both.
class ReportGallery extends StatelessWidget {
  const ReportGallery({super.key, required this.report});

  final VisitReport report;

  @override
  Widget build(BuildContext context) {
    final List<ReportImage>? images = report.images;

    // Asked, and there are none: draw nothing. The caller gates on
    // `visibleImageCount > 0`, so this only catches a count/array disagreement
    // (an upload landing between two reads).
    if (images != null && images.isEmpty) return const SizedBox.shrink();

    if (images == null) {
      // Not fetched. Say how many there are, honestly, and no more.
      return SizedBox(
        height: 92,
        child: ListView.separated(
          scrollDirection: Axis.horizontal,
          itemCount: report.imageCount,
          separatorBuilder: (_, __) => const SizedBox(width: Insets.sm),
          itemBuilder: (BuildContext context, int i) =>
              ReportImageTile(index: i, size: 92),
        ),
      );
    }

    // One photo gets the full width — a lone 92 px square next to a lot of
    // whitespace reads as a thumbnail nobody bothered to lay out.
    if (images.length == 1) {
      return _Hero(report: report, image: images.first, index: 0);
    }

    return GridView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      padding: EdgeInsets.zero,
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 2,
        crossAxisSpacing: Insets.sm,
        mainAxisSpacing: Insets.sm,
        childAspectRatio: 4 / 3,
      ),
      itemCount: images.length,
      itemBuilder: (BuildContext context, int i) => _GridCell(
        report: report,
        image: images[i],
        index: i,
      ),
    );
  }
}

class _Hero extends StatelessWidget {
  const _Hero({
    required this.report,
    required this.image,
    required this.index,
  });

  final VisitReport report;
  final ReportImage image;
  final int index;

  @override
  Widget build(BuildContext context) {
    final String? url = ReportImageTile.resolve(image.url);

    return ClipRRect(
      borderRadius: BorderRadius.circular(Radii.md),
      child: AspectRatio(
        // The server sends width/height, so the box is the photo's own shape
        // and the card does not reflow when the bytes land.
        aspectRatio: image.aspectRatio ?? 4 / 3,
        child: Material(
          color: context.isDark ? AppColors.surfaceAltDark : AppColors.surfaceAlt,
          child: InkWell(
            onTap: () => openReportGallery(context, report, initial: index),
            child: url == null
                ? const Center(child: Icon(Icons.image_not_supported_outlined))
                : Image.network(
                    url,
                    fit: BoxFit.cover,
                    errorBuilder: (_, __, ___) => const Center(
                      child: Icon(Icons.image_not_supported_outlined),
                    ),
                  ),
          ),
        ),
      ),
    );
  }
}

class _GridCell extends StatelessWidget {
  const _GridCell({
    required this.report,
    required this.image,
    required this.index,
  });

  final VisitReport report;
  final ReportImage image;
  final int index;

  @override
  Widget build(BuildContext context) {
    final String? url = ReportImageTile.resolve(image.url);

    return ClipRRect(
      borderRadius: BorderRadius.circular(Radii.md),
      child: Material(
        color: context.isDark ? AppColors.surfaceAltDark : AppColors.surfaceAlt,
        child: InkWell(
          onTap: () => openReportGallery(context, report, initial: index),
          child: Stack(
            fit: StackFit.expand,
            children: <Widget>[
              if (url == null)
                const Center(child: Icon(Icons.image_not_supported_outlined))
              else
                Image.network(
                  url,
                  fit: BoxFit.cover,
                  loadingBuilder: (BuildContext context, Widget child,
                          ImageChunkEvent? p) =>
                      p == null
                          ? child
                          : const Center(
                              child: SizedBox(
                                width: 18,
                                height: 18,
                                child:
                                    CircularProgressIndicator(strokeWidth: 2),
                              ),
                            ),
                  errorBuilder: (_, __, ___) => const Center(
                    child: Icon(Icons.image_not_supported_outlined),
                  ),
                ),
              Positioned(
                top: 4,
                left: 4,
                child: Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                  decoration: BoxDecoration(
                    color: Colors.black54,
                    borderRadius: BorderRadius.circular(Radii.pill),
                  ),
                  child: Text(
                    '${index + 1}',
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 10.5,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Full-screen, swipeable, pinch-zoomable viewer.
Future<void> openReportGallery(
  BuildContext context,
  VisitReport report, {
  int initial = 0,
}) {
  final List<ReportImage> images = report.images ?? const <ReportImage>[];
  if (images.isEmpty) return Future<void>.value();

  return Navigator.of(context).push(
    MaterialPageRoute<void>(
      fullscreenDialog: true,
      builder: (_) => _GalleryPage(
        report: report,
        images: images,
        initial: initial,
      ),
    ),
  );
}

class _GalleryPage extends StatefulWidget {
  const _GalleryPage({
    required this.report,
    required this.images,
    required this.initial,
  });

  final VisitReport report;
  final List<ReportImage> images;
  final int initial;

  @override
  State<_GalleryPage> createState() => _GalleryPageState();
}

class _GalleryPageState extends State<_GalleryPage> {
  late final PageController _pages =
      PageController(initialPage: widget.initial);
  late int _current = widget.initial;

  @override
  void dispose() {
    _pages.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final ReportImage shown = widget.images[_current];

    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        backgroundColor: Colors.black,
        foregroundColor: Colors.white,
        title: Text('${_current + 1} of ${widget.images.length}'),
      ),
      body: Column(
        children: <Widget>[
          Expanded(
            child: PageView.builder(
              controller: _pages,
              onPageChanged: (int i) => setState(() => _current = i),
              itemCount: widget.images.length,
              itemBuilder: (BuildContext context, int i) {
                final String? url =
                    ReportImageTile.resolve(widget.images[i].url);
                return InteractiveViewer(
                  maxScale: 5,
                  child: Center(
                    child: url == null
                        ? const Icon(Icons.image_not_supported_outlined,
                            color: Colors.white54, size: 48)
                        : Image.network(
                            url,
                            fit: BoxFit.contain,
                            loadingBuilder: (BuildContext context, Widget child,
                                    ImageChunkEvent? p) =>
                                p == null
                                    ? child
                                    : const CircularProgressIndicator(
                                        color: Colors.white54),
                            errorBuilder: (_, __, ___) => const Padding(
                              padding: EdgeInsets.all(Insets.xl),
                              child: Text(
                                'This photo could not be loaded.',
                                textAlign: TextAlign.center,
                                style: TextStyle(color: Colors.white70),
                              ),
                            ),
                          ),
                  ),
                );
              },
            ),
          ),
          // What the server actually knows about this photo. Shown rather than
          // guessed: `capturedAt` and the coordinates are null on everything
          // uploaded so far, and an empty row is better than an invented one.
          SafeArea(
            top: false,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(
                  Insets.lg, Insets.sm, Insets.lg, Insets.md),
              child: Row(
                children: <Widget>[
                  Expanded(
                    child: Text(
                      <String>[
                        if (shown.capturedAt != null)
                          Fmt.time(shown.capturedAt!),
                        if (shown.width != null && shown.height != null)
                          '${shown.width}×${shown.height}',
                        if (shown.bytes != null)
                          '${(shown.bytes! / 1024).round()} KB',
                      ].join(' · '),
                      style: const TextStyle(
                        color: Colors.white54,
                        fontSize: 12,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
