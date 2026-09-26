import 'package:flutter/material.dart';

import '../core/theme/app_colors.dart';
import '../core/theme/dimens.dart';
import '../core/theme/theme_ext.dart';

class MultiSelectOption<T> {
  const MultiSelectOption({
    required this.value,
    required this.label,
    this.subtitle,
    this.trailing,
    this.enabled = true,
  });

  final T value;
  final String label;
  final String? subtitle;

  /// Right-aligned hint, e.g. a rating or a range figure.
  final String? trailing;
  final bool enabled;
}

/// Multi-selection sibling of `SelectField`.
///
/// Tapping opens a bottom sheet of checkbox rows with a search box and a
/// select-all / clear pair; the field itself shows the current picks as
/// removable chips so nothing is hidden behind a tap.
///
/// The sheet edits a local copy and only reports back on *Done*, so a
/// half-finished selection cannot leave the form in a state the seller did not
/// confirm. [onChanged] therefore fires at most once per open.
class MultiSelectField<T> extends StatelessWidget {
  const MultiSelectField({
    super.key,
    required this.label,
    required this.hint,
    required this.options,
    required this.values,
    required this.onChanged,
    this.icon,
    this.errorText,
    this.sheetTitle,
    this.searchHint = 'Search',
    this.emptyMessage = 'Nothing to choose from yet.',
    this.chipLabel,
  });

  final String label;
  final String hint;
  final List<MultiSelectOption<T>> options;
  final Set<T> values;
  final ValueChanged<Set<T>> onChanged;
  final IconData? icon;
  final String? errorText;
  final String? sheetTitle;
  final String searchHint;
  final String emptyMessage;

  /// Chip text for a selected value. Defaults to the option's label.
  final String Function(T value)? chipLabel;

  String _labelOf(T value) {
    if (chipLabel != null) return chipLabel!(value);
    for (final MultiSelectOption<T> o in options) {
      if (o.value == value) return o.label;
    }
    return '$value';
  }

  Future<void> _open(BuildContext context) async {
    final Set<T>? picked = await showModalBottomSheet<Set<T>>(
      context: context,
      showDragHandle: true,
      isScrollControlled: true,
      builder: (BuildContext ctx) => _MultiSelectSheet<T>(
        title: sheetTitle ?? label,
        options: options,
        initial: values,
        searchHint: searchHint,
        emptyMessage: emptyMessage,
      ),
    );
    if (picked != null) onChanged(picked);
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final bool hasError = errorText != null;
    final List<T> selected =
        options.map((MultiSelectOption<T> o) => o.value).where(values.contains).toList()
          ..addAll(values.where((T v) =>
              !options.any((MultiSelectOption<T> o) => o.value == v)));

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Text(label, style: theme.textTheme.labelLarge),
        const SizedBox(height: Insets.sm),
        InkWell(
          onTap: () => _open(context),
          borderRadius: BorderRadius.circular(Radii.md),
          child: Container(
            constraints: const BoxConstraints(
              minHeight: Sizes.primaryActionHeight,
            ),
            padding: const EdgeInsets.symmetric(
              horizontal: Insets.lg,
              vertical: Insets.sm,
            ),
            decoration: BoxDecoration(
              color:
                  context.isDark ? AppColors.surfaceAltDark : AppColors.surfaceAlt,
              borderRadius: BorderRadius.circular(Radii.md),
              border: Border.all(
                color: hasError ? AppColors.danger : context.lineColor,
              ),
            ),
            child: Row(
              children: <Widget>[
                if (icon != null) ...<Widget>[
                  Icon(icon, size: 19, color: theme.textTheme.bodySmall?.color),
                  const SizedBox(width: Insets.md),
                ],
                Expanded(
                  child: selected.isEmpty
                      ? Padding(
                          padding: const EdgeInsets.symmetric(vertical: 9),
                          child: Text(hint, style: theme.textTheme.bodyMedium),
                        )
                      : Wrap(
                          spacing: Insets.xs,
                          runSpacing: Insets.xs,
                          children: selected
                              .map(
                                (T v) => _Chip(
                                  label: _labelOf(v),
                                  onRemove: () => onChanged(
                                    values.where((T x) => x != v).toSet(),
                                  ),
                                ),
                              )
                              .toList(growable: false),
                        ),
                ),
                const SizedBox(width: Insets.sm),
                const Icon(Icons.expand_more_rounded, size: 20),
              ],
            ),
          ),
        ),
        if (hasError)
          Padding(
            padding: const EdgeInsets.only(top: 6, left: Insets.xs),
            child: Text(
              errorText!,
              style:
                  theme.textTheme.bodySmall?.copyWith(color: AppColors.danger),
            ),
          ),
      ],
    );
  }
}

class _Chip extends StatelessWidget {
  const _Chip({required this.label, required this.onRemove});

  final String label;
  final VoidCallback onRemove;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(Insets.sm, 4, 4, 4),
      decoration: BoxDecoration(
        color: AppColors.primary.withValues(alpha: context.isDark ? 0.22 : 0.1),
        borderRadius: BorderRadius.circular(Radii.pill),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Text(
            label,
            style: TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w600,
              color: context.isDark ? AppColors.primarySoft : AppColors.primaryDark,
            ),
          ),
          const SizedBox(width: 2),
          InkWell(
            onTap: onRemove,
            borderRadius: BorderRadius.circular(Radii.pill),
            child: const Padding(
              padding: EdgeInsets.all(2),
              child: Icon(Icons.close_rounded, size: 13),
            ),
          ),
        ],
      ),
    );
  }
}

class _MultiSelectSheet<T> extends StatefulWidget {
  const _MultiSelectSheet({
    required this.title,
    required this.options,
    required this.initial,
    required this.searchHint,
    required this.emptyMessage,
  });

  final String title;
  final List<MultiSelectOption<T>> options;
  final Set<T> initial;
  final String searchHint;
  final String emptyMessage;

  @override
  State<_MultiSelectSheet<T>> createState() => _MultiSelectSheetState<T>();
}

class _MultiSelectSheetState<T> extends State<_MultiSelectSheet<T>> {
  late final Set<T> _picked = <T>{...widget.initial};
  String _query = '';

  List<MultiSelectOption<T>> get _visible {
    final String q = _query.trim().toLowerCase();
    if (q.isEmpty) return widget.options;
    return widget.options
        .where((MultiSelectOption<T> o) =>
            o.label.toLowerCase().contains(q) ||
            (o.subtitle ?? '').toLowerCase().contains(q))
        .toList(growable: false);
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final List<MultiSelectOption<T>> rows = _visible;
    final Iterable<T> selectable = widget.options
        .where((MultiSelectOption<T> o) => o.enabled)
        .map((MultiSelectOption<T> o) => o.value);
    final bool allPicked =
        selectable.isNotEmpty && selectable.every(_picked.contains);

    return SafeArea(
      child: ConstrainedBox(
        constraints: BoxConstraints(
          maxHeight: MediaQuery.sizeOf(context).height * 0.85,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Padding(
              padding: const EdgeInsets.fromLTRB(Insets.xl, 0, Insets.xl, Insets.sm),
              child: Row(
                children: <Widget>[
                  Expanded(
                    child: Text(widget.title, style: theme.textTheme.titleLarge),
                  ),
                  TextButton(
                    onPressed: selectable.isEmpty
                        ? null
                        : () => setState(() {
                              if (allPicked) {
                                _picked.removeAll(selectable);
                              } else {
                                _picked.addAll(selectable);
                              }
                            }),
                    child: Text(allPicked ? 'Clear all' : 'Select all'),
                  ),
                ],
              ),
            ),
            if (widget.options.length > 6)
              Padding(
                padding: const EdgeInsets.fromLTRB(Insets.xl, 0, Insets.xl, Insets.sm),
                child: TextField(
                  autofocus: false,
                  onChanged: (String v) => setState(() => _query = v),
                  decoration: InputDecoration(
                    hintText: widget.searchHint,
                    prefixIcon: const Icon(Icons.search_rounded),
                  ),
                ),
              ),
            Flexible(
              child: rows.isEmpty
                  ? Padding(
                      padding: const EdgeInsets.all(Insets.xxl),
                      child: Text(
                        widget.options.isEmpty
                            ? widget.emptyMessage
                            : 'Nothing matches "$_query".',
                        style: theme.textTheme.bodyMedium,
                      ),
                    )
                  : ListView.builder(
                      shrinkWrap: true,
                      itemCount: rows.length,
                      itemBuilder: (BuildContext context, int i) {
                        final MultiSelectOption<T> o = rows[i];
                        return CheckboxListTile(
                          value: _picked.contains(o.value),
                          onChanged: o.enabled
                              ? (bool? on) => setState(() {
                                    if (on == true) {
                                      _picked.add(o.value);
                                    } else {
                                      _picked.remove(o.value);
                                    }
                                  })
                              : null,
                          controlAffinity: ListTileControlAffinity.leading,
                          title: Text(o.label),
                          subtitle: o.subtitle == null ? null : Text(o.subtitle!),
                          secondary: o.trailing == null
                              ? null
                              : Text(
                                  o.trailing!,
                                  style: theme.textTheme.labelSmall,
                                ),
                        );
                      },
                    ),
            ),
            Padding(
              padding: const EdgeInsets.all(Insets.lg),
              child: SizedBox(
                width: double.infinity,
                height: Sizes.primaryActionHeight,
                child: FilledButton(
                  onPressed: () => Navigator.pop(context, _picked),
                  child: Text(
                    _picked.isEmpty
                        ? 'Done'
                        : 'Done · ${_picked.length} selected',
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
