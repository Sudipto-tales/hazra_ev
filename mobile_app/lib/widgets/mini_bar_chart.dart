import 'package:flutter/material.dart';

import '../core/theme/app_colors.dart';
import '../core/theme/dimens.dart';
import '../core/utils/formatters.dart';
import '../data/models/models.dart';

/// Dependency-free daily bar chart. Used only where a trend is genuinely
/// easier to read than numbers — distance per day.
class MiniBarChart extends StatefulWidget {
  const MiniBarChart({
    super.key,
    required this.data,
    this.height = 150,
    this.barColor = AppColors.primary,
    this.unit = 'km',
  });

  final List<DailyMetric> data;
  final double height;
  final Color barColor;
  final String unit;

  @override
  State<MiniBarChart> createState() => _MiniBarChartState();
}

class _MiniBarChartState extends State<MiniBarChart> {
  int? _selected;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    if (widget.data.isEmpty) {
      return SizedBox(
        height: widget.height,
        child: Center(
          child: Text('No data in this range', style: theme.textTheme.bodySmall),
        ),
      );
    }

    // Cap the series so a long month stays legible on a phone.
    final List<DailyMetric> series = widget.data.length > 31
        ? widget.data.sublist(widget.data.length - 31)
        : widget.data;

    final double max = series
        .map((DailyMetric d) => d.value)
        .reduce((double a, double b) => a > b ? a : b);
    final DailyMetric? active =
        _selected != null && _selected! < series.length ? series[_selected!] : null;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        SizedBox(
          height: 20,
          child: active == null
              ? Text(
                  'Tap a bar for detail · peak ${max.toStringAsFixed(1)} ${widget.unit}',
                  style: theme.textTheme.bodySmall,
                )
              : Text(
                  '${Fmt.mediumDate(active.date)} · '
                  '${active.value.toStringAsFixed(1)} ${widget.unit} · '
                  '${active.secondaryValue} visits',
                  style: theme.textTheme.bodySmall
                      ?.copyWith(color: widget.barColor, fontWeight: FontWeight.w600),
                ),
        ),
        const SizedBox(height: Insets.md),
        SizedBox(
          height: widget.height,
          child: LayoutBuilder(
            builder: (BuildContext context, BoxConstraints constraints) {
              final double gap = series.length > 20 ? 2 : 4;
              final double barWidth =
                  ((constraints.maxWidth - gap * (series.length - 1)) /
                          series.length)
                      .clamp(3.0, 26.0);
              return Row(
                crossAxisAlignment: CrossAxisAlignment.end,
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: List<Widget>.generate(series.length, (int i) {
                  final DailyMetric d = series[i];
                  final double ratio = max == 0 ? 0 : d.value / max;
                  final bool isActive = _selected == i;
                  return GestureDetector(
                    behavior: HitTestBehavior.opaque,
                    onTap: () => setState(
                      () => _selected = isActive ? null : i,
                    ),
                    child: SizedBox(
                      width: barWidth,
                      height: widget.height,
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.end,
                        children: <Widget>[
                          Container(
                            width: barWidth,
                            height: (widget.height - 18) * ratio + 3,
                            decoration: BoxDecoration(
                              color: isActive
                                  ? widget.barColor
                                  : widget.barColor.withValues(alpha: 0.28),
                              borderRadius: BorderRadius.circular(4),
                            ),
                          ),
                          const SizedBox(height: 6),
                          if (series.length <= 16 || i % 5 == 0)
                            Text(
                              '${d.date.day}',
                              style: theme.textTheme.labelSmall
                                  ?.copyWith(fontSize: 9),
                            )
                          else
                            const SizedBox(height: 11),
                        ],
                      ),
                    ),
                  );
                }),
              );
            },
          ),
        ),
      ],
    );
  }
}

/// Horizontal proportion bar (present / partial / absent).
class ProportionBar extends StatelessWidget {
  const ProportionBar({super.key, required this.segments});

  final List<ProportionSegment> segments;

  @override
  Widget build(BuildContext context) {
    final double total =
        segments.fold<double>(0, (double s, ProportionSegment e) => s + e.value);
    if (total == 0) {
      return const SizedBox(height: 10);
    }
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        ClipRRect(
          borderRadius: BorderRadius.circular(Radii.pill),
          child: SizedBox(
            height: 10,
            child: Row(
              children: segments
                  .where((ProportionSegment s) => s.value > 0)
                  .map(
                    (ProportionSegment s) => Expanded(
                      flex: (s.value * 100).round().clamp(1, 100000).toInt(),
                      child: Container(color: s.color),
                    ),
                  )
                  .toList(growable: false),
            ),
          ),
        ),
        const SizedBox(height: Insets.md),
        Wrap(
          spacing: Insets.lg,
          runSpacing: Insets.sm,
          children: segments
              .map(
                (ProportionSegment s) => Row(
                  mainAxisSize: MainAxisSize.min,
                  children: <Widget>[
                    Container(
                      width: 8,
                      height: 8,
                      decoration:
                          BoxDecoration(color: s.color, shape: BoxShape.circle),
                    ),
                    const SizedBox(width: 6),
                    Text(
                      '${s.label} · ${s.value.toStringAsFixed(0)}',
                      style: Theme.of(context).textTheme.bodySmall,
                    ),
                  ],
                ),
              )
              .toList(growable: false),
        ),
      ],
    );
  }
}

class ProportionSegment {
  const ProportionSegment({
    required this.label,
    required this.value,
    required this.color,
  });

  final String label;
  final double value;
  final Color color;
}
