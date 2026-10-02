import 'dart:math' show Random;

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/dimens.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/models/models.dart';
import '../../../widgets/rating_bar.dart';
import '../../../widgets/stat_tile.dart';
import '../../../widgets/states.dart';

/// The end-of-day declaration. Returns the draft to submit, or null if the
/// employee backed out.
///
/// Two things are being collected here and they are not the same kind of thing.
/// The distance and visit counts are a **claim** — what the employee says the
/// day was. The GPS figures shown above them are a **measurement**. They are
/// deliberately displayed side by side and never merged: the gap between them
/// is the only reason to ask at all.
///
/// The form is only ever reached with a live connection and a valid GPS fix —
/// see `TrackingController.checkEndDay`. Nothing here queues offline.
Future<DayCloseoutDraft?> showDayCloseoutSheet(
  BuildContext context, {
  required DaySummary summary,
  required Duration workedToday,
  required int sessionCount,
  required DateTime? joiningTime,
  required int visitsDetected,
  required DateTime endedAt,
}) {
  return showModalBottomSheet<DayCloseoutDraft>(
    context: context,
    isScrollControlled: true,
    showDragHandle: true,
    isDismissible: false,
    enableDrag: false,
    builder: (BuildContext context) => _DayCloseoutSheet(
      summary: summary,
      workedToday: workedToday,
      sessionCount: sessionCount,
      joiningTime: joiningTime,
      visitsDetected: visitsDetected,
      endedAt: endedAt,
    ),
  );
}

class _DayCloseoutSheet extends StatefulWidget {
  const _DayCloseoutSheet({
    required this.summary,
    required this.workedToday,
    required this.sessionCount,
    required this.joiningTime,
    required this.visitsDetected,
    required this.endedAt,
  });

  final DaySummary summary;
  final Duration workedToday;
  final int sessionCount;
  final DateTime? joiningTime;
  final int visitsDetected;
  final DateTime endedAt;

  @override
  State<_DayCloseoutSheet> createState() => _DayCloseoutSheetState();
}

class _DayCloseoutSheetState extends State<_DayCloseoutSheet> {
  final GlobalKey<FormState> _form = GlobalKey<FormState>();
  final TextEditingController _km = TextEditingController();
  final TextEditingController _visits = TextEditingController();
  final TextEditingController _feedback = TextEditingController();

  int _rating = 0;
  bool _ratingError = false;
  final Set<DayFeedbackTag> _tags = <DayFeedbackTag>{};

  @override
  void dispose() {
    _km.dispose();
    _visits.dispose();
    _feedback.dispose();
    super.dispose();
  }

  double? get _declaredKm => double.tryParse(_km.text.trim());
  int? get _declaredVisits => int.tryParse(_visits.text.trim());

  /// Signed gap against the GPS figure, or null when there is nothing to
  /// compare against yet.
  double? get _deviation {
    final double? declared = _declaredKm;
    final double measured = widget.summary.distanceKm;
    if (declared == null || measured <= 0) return null;
    return (declared - measured) / measured * 100;
  }

  bool get _distanceLooksOff {
    final double? d = _deviation;
    return d != null && d.abs() > DayCloseoutLimits.deviationWarnPercent;
  }

  /// Fewer visits than reports filed is arithmetically impossible — a report is
  /// written at a visit. Still a warning, not a block: it is the employee's
  /// declaration, and refusing it would only teach them to type a number that
  /// passes rather than the number that is true.
  bool get _visitsLookOff {
    final int? declared = _declaredVisits;
    return declared != null && declared < widget.summary.reportsSubmitted;
  }

  void _submit() {
    final bool formOk = _form.currentState?.validate() ?? false;
    final bool ratingOk = _rating > 0;
    if (!ratingOk) setState(() => _ratingError = true);
    if (!formOk || !ratingOk) return;

    Navigator.pop(
      context,
      DayCloseoutDraft(
        clientId: _clientId(),
        endedAt: widget.endedAt,
        declaredDistanceKm: _declaredKm!,
        declaredVisits: _declaredVisits!,
        rating: _rating,
        tags: _tags.toList(growable: false),
        feedback: _feedback.text.trim().isEmpty ? null : _feedback.text.trim(),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final double maxHeight = MediaQuery.sizeOf(context).height * 0.92;

    return SafeArea(
      child: ConstrainedBox(
        constraints: BoxConstraints(maxHeight: maxHeight),
        child: Padding(
          padding: EdgeInsets.only(
            bottom: MediaQuery.viewInsetsOf(context).bottom,
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              Expanded(
                child: SingleChildScrollView(
                  padding: const EdgeInsets.fromLTRB(
                    Insets.xl,
                    0,
                    Insets.xl,
                    Insets.lg,
                  ),
                  child: Form(
                    key: _form,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Text('End your day', style: theme.textTheme.headlineSmall),
                        const SizedBox(height: 4),
                        Text(
                          'Tell us how the day went. Once you submit this, today '
                          'is closed.',
                          style: theme.textTheme.bodyMedium,
                        ),
                        const SizedBox(height: Insets.lg),
                        _MeasuredBlock(
                          summary: widget.summary,
                          workedToday: widget.workedToday,
                          sessionCount: widget.sessionCount,
                          joiningTime: widget.joiningTime,
                        ),
                        const SizedBox(height: Insets.xl),
                        const _Label(
                          'Total distance you travelled',
                          hint: 'As per your own reading',
                        ),
                        TextFormField(
                          controller: _km,
                          keyboardType: const TextInputType.numberWithOptions(
                            decimal: true,
                          ),
                          inputFormatters: <TextInputFormatter>[
                            FilteringTextInputFormatter.allow(
                              RegExp(r'^\d{0,4}\.?\d{0,1}'),
                            ),
                          ],
                          onChanged: (_) => setState(() {}),
                          decoration: InputDecoration(
                            hintText: '0.0',
                            suffixText: 'km',
                            helperText:
                                'GPS recorded ${Fmt.km(widget.summary.distanceKm)}',
                          ),
                          validator: _validateKm,
                        ),
                        const SizedBox(height: Insets.lg),
                        const _Label(
                          'Total visits you made',
                          hint: 'Companies or branches you actually went to',
                        ),
                        TextFormField(
                          controller: _visits,
                          keyboardType: TextInputType.number,
                          inputFormatters: <TextInputFormatter>[
                            FilteringTextInputFormatter.digitsOnly,
                          ],
                          onChanged: (_) => setState(() {}),
                          decoration: InputDecoration(
                            hintText: '0',
                            helperText: widget.visitsDetected == 0
                                ? 'No visits detected from your route'
                                : '${widget.visitsDetected} detected from your route',
                          ),
                          validator: _validateVisits,
                        ),
                        if (_distanceLooksOff || _visitsLookOff) ...<Widget>[
                          const SizedBox(height: Insets.lg),
                          AlertBanner(
                            icon: Icons.info_outline_rounded,
                            tone: AppColors.warning,
                            title: 'Worth a second look',
                            message: _mismatchMessage(),
                          ),
                        ],
                        const SizedBox(height: Insets.xl),
                        const _Label('How was your day?'),
                        RatingBar(
                          value: _rating,
                          showError: _ratingError,
                          onChanged: (int v) => setState(() {
                            _rating = v;
                            _ratingError = false;
                          }),
                        ),
                        const SizedBox(height: Insets.xl),
                        const _Label(
                          'Anything that got in the way?',
                          optional: true,
                        ),
                        Wrap(
                          spacing: Insets.sm,
                          runSpacing: Insets.sm,
                          children: <Widget>[
                            for (final DayFeedbackTag tag
                                in DayFeedbackTag.values)
                              FilterChip(
                                label: Text(tag.label),
                                selected: _tags.contains(tag),
                                onSelected: (bool on) => setState(() {
                                  if (on) {
                                    _tags.add(tag);
                                  } else {
                                    _tags.remove(tag);
                                  }
                                }),
                              ),
                          ],
                        ),
                        const SizedBox(height: Insets.lg),
                        const _Label('Feedback', optional: true),
                        TextFormField(
                          controller: _feedback,
                          minLines: 3,
                          maxLines: 5,
                          maxLength: DayCloseoutLimits.feedbackMaxChars,
                          textCapitalization: TextCapitalization.sentences,
                          decoration: const InputDecoration(
                            hintText:
                                'Anything your manager should know about today',
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
              _Footer(onCancel: () => Navigator.pop(context), onSubmit: _submit),
            ],
          ),
        ),
      ),
    );
  }

  String? _validateKm(String? raw) {
    final String value = (raw ?? '').trim();
    if (value.isEmpty) return 'Enter the distance you travelled';
    final double? km = double.tryParse(value);
    if (km == null) return 'Enter a number';
    if (km < 0) return 'Distance cannot be negative';
    if (km > DayCloseoutLimits.maxDistanceKm) {
      return 'That is over ${DayCloseoutLimits.maxDistanceKm.toStringAsFixed(0)} km — check the reading';
    }
    return null;
  }

  String? _validateVisits(String? raw) {
    final String value = (raw ?? '').trim();
    if (value.isEmpty) return 'Enter how many visits you made';
    final int? visits = int.tryParse(value);
    if (visits == null) return 'Enter a whole number';
    if (visits > DayCloseoutLimits.maxVisits) {
      return 'That is over ${DayCloseoutLimits.maxVisits} — check the count';
    }
    return null;
  }

  String _mismatchMessage() {
    final List<String> parts = <String>[];
    if (_distanceLooksOff) {
      final double d = _deviation!;
      parts.add(
        'Your distance is ${d.abs().toStringAsFixed(0)}% '
        '${d > 0 ? 'above' : 'below'} the ${Fmt.km(widget.summary.distanceKm)} '
        'GPS recorded.',
      );
    }
    if (_visitsLookOff) {
      parts.add(
        'You filed ${widget.summary.reportsSubmitted} reports today but entered '
        'fewer visits.',
      );
    }
    parts.add('You can still submit — this is only a heads-up.');
    return parts.join(' ');
  }
}

/// What was measured, stated before anything is typed. An unanchored claim is
/// a guess; this is what makes it a comparison.
class _MeasuredBlock extends StatelessWidget {
  const _MeasuredBlock({
    required this.summary,
    required this.workedToday,
    required this.sessionCount,
    required this.joiningTime,
  });

  final DaySummary summary;
  final Duration workedToday;
  final int sessionCount;
  final DateTime? joiningTime;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Container(
      padding: const EdgeInsets.symmetric(
        horizontal: Insets.lg,
        vertical: Insets.md,
      ),
      decoration: BoxDecoration(
        color: theme.colorScheme.surfaceContainerHighest.withValues(alpha: 0.5),
        borderRadius: BorderRadius.circular(Radii.lg),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text(
            'What was tracked',
            style: theme.textTheme.labelLarge?.copyWith(
              color: AppColors.textSecondary,
            ),
          ),
          const SizedBox(height: Insets.xs),
          KeyValueRow(
            label: 'Joining time',
            value: Fmt.time(joiningTime),
            dense: true,
          ),
          KeyValueRow(
            label: 'Worked today',
            value: Fmt.duration(workedToday),
            dense: true,
          ),
          KeyValueRow(label: 'Sessions', value: '$sessionCount', dense: true),
          KeyValueRow(
            label: 'Distance (GPS)',
            value: Fmt.km(summary.distanceKm),
            dense: true,
          ),
          KeyValueRow(
            label: 'Visits detected',
            value: '${summary.companiesVisited}',
            dense: true,
          ),
          KeyValueRow(
            label: 'Reports submitted',
            value: '${summary.reportsSubmitted}',
            dense: true,
          ),
        ],
      ),
    );
  }
}

class _Footer extends StatelessWidget {
  const _Footer({required this.onCancel, required this.onSubmit});

  final VoidCallback onCancel;
  final VoidCallback onSubmit;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Container(
      padding: const EdgeInsets.fromLTRB(Insets.xl, Insets.md, Insets.xl, Insets.md),
      decoration: BoxDecoration(
        color: theme.colorScheme.surface,
        border: Border(
          top: BorderSide(color: theme.dividerColor.withValues(alpha: 0.6)),
        ),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              const Icon(Icons.lock_outline_rounded,
                  size: 16, color: AppColors.danger),
              const SizedBox(width: Insets.sm),
              Expanded(
                child: Text(
                  'Submitting closes today for good. Only your admin can reopen it.',
                  style: theme.textTheme.bodySmall
                      ?.copyWith(color: AppColors.danger),
                ),
              ),
            ],
          ),
          const SizedBox(height: Insets.md),
          Row(
            children: <Widget>[
              Expanded(
                child: SizedBox(
                  height: Sizes.touchTarget,
                  child: OutlinedButton(
                    onPressed: onCancel,
                    child: const Text('Keep working'),
                  ),
                ),
              ),
              const SizedBox(width: Insets.md),
              Expanded(
                flex: 2,
                child: SizedBox(
                  height: Sizes.touchTarget,
                  child: FilledButton.icon(
                    onPressed: onSubmit,
                    style: FilledButton.styleFrom(
                      backgroundColor: AppColors.danger,
                    ),
                    icon: const Icon(Icons.check_rounded, size: 20),
                    label: const Text('Close the day'),
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _Label extends StatelessWidget {
  const _Label(this.text, {this.hint, this.optional = false});

  final String text;
  final String? hint;
  final bool optional;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.only(bottom: Insets.sm),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              Text(text, style: theme.textTheme.titleMedium?.copyWith(fontSize: 14.5)),
              if (optional) ...<Widget>[
                const SizedBox(width: Insets.sm),
                Text(
                  'optional',
                  style: theme.textTheme.bodySmall
                      ?.copyWith(color: AppColors.textTertiary),
                ),
              ],
            ],
          ),
          if (hint != null)
            Text(
              hint!,
              style: theme.textTheme.bodySmall
                  ?.copyWith(color: AppColors.textSecondary),
            ),
        ],
      ),
    );
  }
}

/// Idempotency key for the submit. Generated here, once, so a retry of the same
/// declaration cannot close the day twice.
String _clientId() {
  final int now = DateTime.now().microsecondsSinceEpoch;
  final int salt = Random().nextInt(1 << 32);
  return '$now-${salt.toRadixString(16)}';
}
