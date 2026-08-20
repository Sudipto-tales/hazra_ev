import 'package:flutter/material.dart';

import '../core/theme/app_colors.dart';
import '../core/theme/dimens.dart';
import '../core/theme/theme_ext.dart';

class SelectOption<T> {
  const SelectOption({required this.value, required this.label, this.subtitle});

  final T value;
  final String label;
  final String? subtitle;
}

/// Tap-to-open picker used instead of [DropdownButtonFormField].
///
/// Keeps a full-width 56dp touch target, shows the selection inline and opens a
/// bottom sheet with comfortably sized rows.
class SelectField<T> extends StatelessWidget {
  const SelectField({
    super.key,
    required this.label,
    required this.hint,
    required this.options,
    required this.value,
    required this.onChanged,
    this.icon,
    this.errorText,
    this.clearable = false,
    this.sheetTitle,
  });

  final String label;
  final String hint;
  final List<SelectOption<T>> options;
  final T? value;
  final ValueChanged<T?> onChanged;
  final IconData? icon;
  final String? errorText;
  final bool clearable;
  final String? sheetTitle;

  SelectOption<T>? get _selected {
    for (final SelectOption<T> o in options) {
      if (o.value == value) return o;
    }
    return null;
  }

  Future<void> _open(BuildContext context) async {
    final T? picked = await showModalBottomSheet<T>(
      context: context,
      showDragHandle: true,
      isScrollControlled: true,
      builder: (BuildContext context) {
        return SafeArea(
          child: ConstrainedBox(
            constraints: BoxConstraints(
              maxHeight: MediaQuery.sizeOf(context).height * 0.7,
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Padding(
                  padding: const EdgeInsets.fromLTRB(
                    Insets.xl,
                    0,
                    Insets.xl,
                    Insets.md,
                  ),
                  child: Text(
                    sheetTitle ?? label,
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                ),
                Flexible(
                  child: ListView.builder(
                    shrinkWrap: true,
                    itemCount: options.length,
                    itemBuilder: (BuildContext context, int i) {
                      final SelectOption<T> o = options[i];
                      final bool selected = o.value == value;
                      return ListTile(
                        title: Text(o.label),
                        subtitle:
                            o.subtitle == null ? null : Text(o.subtitle!),
                        trailing: selected
                            ? const Icon(Icons.check_rounded,
                                color: AppColors.primary)
                            : null,
                        onTap: () => Navigator.pop(context, o.value),
                      );
                    },
                  ),
                ),
                const SizedBox(height: Insets.md),
              ],
            ),
          ),
        );
      },
    );
    if (picked != null) onChanged(picked);
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final SelectOption<T>? sel = _selected;
    final bool hasError = errorText != null;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Text(label, style: theme.textTheme.labelLarge),
        const SizedBox(height: Insets.sm),
        InkWell(
          onTap: () => _open(context),
          borderRadius: BorderRadius.circular(Radii.md),
          child: Container(
            height: Sizes.primaryActionHeight,
            padding: const EdgeInsets.symmetric(horizontal: Insets.lg),
            decoration: BoxDecoration(
              color: context.isDark
                  ? AppColors.surfaceAltDark
                  : AppColors.surfaceAlt,
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
                  child: Text(
                    sel?.label ?? hint,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: sel == null
                        ? theme.textTheme.bodyMedium
                        : theme.textTheme.bodyLarge,
                  ),
                ),
                if (clearable && sel != null)
                  InkWell(
                    onTap: () => onChanged(null),
                    borderRadius: BorderRadius.circular(Radii.pill),
                    child: const Padding(
                      padding: EdgeInsets.all(4),
                      child: Icon(Icons.close_rounded, size: 17),
                    ),
                  )
                else
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
              style: theme.textTheme.bodySmall
                  ?.copyWith(color: AppColors.danger),
            ),
          ),
      ],
    );
  }
}
