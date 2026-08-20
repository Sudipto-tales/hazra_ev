import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/theme/app_colors.dart';
import '../../core/theme/dimens.dart';
import '../../core/theme/theme_ext.dart';
import '../../core/utils/formatters.dart';
import '../../data/mock/mock_data.dart';
import '../../data/models/models.dart';
import '../../data/repositories/employee_repository.dart';
import '../../state/app_scope.dart';
import '../../widgets/app_card.dart';
import '../../widgets/collapsible_card.dart';
import '../../widgets/multi_select_field.dart';
import '../../widgets/select_field.dart';
import '../../widgets/stat_tile.dart';
import '../../widgets/states.dart';
import 'widgets/image_tile.dart';
import 'widgets/product_sale_card.dart';
import 'widgets/product_widgets.dart';

/// Report composer, split into collapsible sections.
///
/// Company and branch are **typed, not picked**: a seller's route is not fixed,
/// so the shop may not be on any master list. Tapping a detected visit fills
/// both fields and records the ids behind them; editing either field afterwards
/// drops that link, because the report no longer describes the detected place.
///
/// Session, GPS and timestamp are attached silently.
class CreateReportPage extends StatefulWidget {
  const CreateReportPage({super.key, this.visit});

  /// Pre-fills company/branch when opened from a visit.
  final CompanyVisit? visit;

  @override
  State<CreateReportPage> createState() => _CreateReportPageState();
}

enum _Section { visit, report, products, extras }

class _CreateReportPageState extends State<CreateReportPage> {
  final GlobalKey<FormState> _form = GlobalKey<FormState>();

  late final TextEditingController _company =
      TextEditingController(text: _visitCompanyName ?? '');
  late final TextEditingController _branch =
      TextEditingController(text: _visitBranchName ?? '');
  final TextEditingController _title = TextEditingController();
  final TextEditingController _body = TextEditingController();
  final TextEditingController _deal = TextEditingController();
  final TextEditingController _payment = TextEditingController();

  /// Kept only while the typed names still match the linked visit.
  late String? _companyId = widget.visit?.companyId;
  late String? _branchId = widget.visit?.branchId;
  late String? _visitId = widget.visit?.id;

  DateTime? _followUp;
  int _images = 0;

  final Set<_Section> _open = <_Section>{_Section.visit, _Section.report};

  // ------------------------------------------------------------ EV sale card

  ProductCategory? _category;
  List<Product> _catalog = const <Product>[];
  bool _loadingCatalog = false;
  Object? _catalogError;

  /// Every product ever fetched in this form, so a line from a category the
  /// seller has since switched away from still renders.
  final Map<String, Product> _known = <String, Product>{};

  /// Sale lines in the order they were added.
  final List<ProductSaleLine> _lines = <ProductSaleLine>[];

  bool _submitting = false;
  double _progress = 0;
  bool _done = false;

  String? get _visitCompanyName => widget.visit == null
      ? null
      : MockData.companyName(widget.visit!.companyId);

  String? get _visitBranchName => widget.visit == null
      ? null
      : MockData.branchName(widget.visit!.companyId, widget.visit!.branchId);

  int get _unitsSold =>
      _lines.fold<int>(0, (int sum, ProductSaleLine l) => sum + l.units);

  @override
  void dispose() {
    _company.dispose();
    _branch.dispose();
    _title.dispose();
    _body.dispose();
    _deal.dispose();
    _payment.dispose();
    super.dispose();
  }

  // --------------------------------------------------------------- behaviour

  /// The typed name no longer describes the detected visit — forget the link so
  /// the report is not filed against the wrong branch.
  void _unlinkVisit() {
    if (_companyId == null && _branchId == null && _visitId == null) return;
    setState(() {
      _companyId = null;
      _branchId = null;
      _visitId = null;
    });
  }

  void _linkVisit(CompanyVisit v) {
    setState(() {
      _visitId = v.id;
      _companyId = v.companyId;
      _branchId = v.branchId;
      _company.text = MockData.companyName(v.companyId);
      _branch.text = MockData.branchName(v.companyId, v.branchId) ?? '';
    });
  }

  Future<void> _loadCatalog(ProductCategory category) async {
    setState(() {
      _category = category;
      _loadingCatalog = true;
      _catalogError = null;
    });
    try {
      final List<Product> rows = await AppScope.of(context)
          .repository
          .products(category: category);
      if (!mounted) return;
      setState(() {
        _catalog = rows;
        for (final Product p in rows) {
          _known[p.id] = p;
        }
        _loadingCatalog = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _catalogError = e;
        _loadingCatalog = false;
      });
    }
  }

  /// Reconcile the multi-select against [_lines] for the current category only,
  /// so switching category never discards what was already logged.
  void _applySelection(Set<String> ids) {
    setState(() {
      _lines.removeWhere((ProductSaleLine l) =>
          l.category == _category && !ids.contains(l.productId));

      for (final String id in ids) {
        if (_lines.any((ProductSaleLine l) => l.productId == id)) continue;
        final Product? p = _known[id];
        if (p == null) continue;
        final ProductColor c = p.colors.firstWhere(
          (ProductColor c) => c.inStock,
          orElse: () => p.colors.first,
        );
        _lines.add(
          ProductSaleLine(
            productId: p.id,
            productName: p.displayName,
            category: p.category,
            colorName: c.name,
            colorArgb: c.argb,
            units: 1,
          ),
        );
      }
    });
  }

  Future<void> _pickImage() async {
    final ImageSourceChoice? choice =
        await showModalBottomSheet<ImageSourceChoice>(
      context: context,
      showDragHandle: true,
      builder: (BuildContext context) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            ListTile(
              leading: const Icon(Icons.photo_camera_outlined),
              title: const Text('Take a photo'),
              subtitle: const Text('Camera'),
              onTap: () => Navigator.pop(context, ImageSourceChoice.camera),
            ),
            ListTile(
              leading: const Icon(Icons.photo_library_outlined),
              title: const Text('Choose from gallery'),
              subtitle: const Text('Multiple selection supported'),
              onTap: () => Navigator.pop(context, ImageSourceChoice.gallery),
            ),
            const SizedBox(height: Insets.md),
          ],
        ),
      ),
    );
    if (choice == null) return;
    setState(() {
      _images += choice == ImageSourceChoice.gallery ? 2 : 1;
    });
  }

  /// A `TextFormField` inside a collapsed section is not mounted, so the `Form`
  /// cannot see it. Open every section and wait one frame before validating.
  Future<void> _openEverything() {
    setState(() => _open.addAll(_Section.values));
    final Completer<void> settled = Completer<void>();
    WidgetsBinding.instance.addPostFrameCallback((_) => settled.complete());
    return settled.future;
  }

  Future<void> _submit() async {
    await _openEverything();
    if (!mounted) return;
    if (!(_form.currentState?.validate() ?? false)) return;

    setState(() {
      _submitting = true;
      _progress = 0;
    });

    // Simulated upload progress — replace with real multipart progress events.
    final Timer ticker = Timer.periodic(
      const Duration(milliseconds: 110),
      (Timer t) {
        if (!mounted) return;
        setState(() => _progress = (_progress + 0.07).clamp(0.0, 0.95));
      },
    );

    try {
      await AppScope.of(context).repository.submitReport(
            ReportDraft(
              companyName: _company.text.trim(),
              branchName:
                  _branch.text.trim().isEmpty ? null : _branch.text.trim(),
              companyId: _companyId,
              branchId: _branchId,
              title: _title.text.trim(),
              body: _body.text.trim(),
              imageCount: _images,
              visitId: _visitId,
              dealValue: _deal.text.trim().isEmpty ? null : _deal.text.trim(),
              followUpOn: _followUp,
              sales: List<ProductSaleLine>.unmodifiable(_lines),
              paymentReceived:
                  _payment.text.trim().isEmpty ? null : _payment.text.trim(),
            ),
          );
      ticker.cancel();
      if (!mounted) return;
      setState(() {
        _progress = 1;
        _submitting = false;
        _done = true;
      });
    } catch (_) {
      ticker.cancel();
      if (!mounted) return;
      setState(() => _submitting = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: const Text('Upload failed — saved as a draft on this device'),
          action: SnackBarAction(label: 'Retry', onPressed: _submit),
        ),
      );
    }
  }

  void _toggle(_Section s) => setState(() {
        if (!_open.remove(s)) _open.add(s);
      });

  // ------------------------------------------------------------------- build

  @override
  Widget build(BuildContext context) {
    if (_done) return _SuccessView(onClose: () => Navigator.pop(context));

    final ThemeData theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(title: const Text('New report')),
      bottomNavigationBar: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(Insets.lg),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              if (_submitting) ...<Widget>[
                ClipRRect(
                  borderRadius: BorderRadius.circular(Radii.pill),
                  child: LinearProgressIndicator(value: _progress, minHeight: 6),
                ),
                const SizedBox(height: Insets.sm),
                Text(
                  'Uploading ${(_progress * 100).round()}% · $_images image'
                  '${_images == 1 ? '' : 's'}',
                  style: theme.textTheme.bodySmall,
                ),
                const SizedBox(height: Insets.md),
              ],
              SizedBox(
                width: double.infinity,
                height: Sizes.primaryActionHeight,
                child: FilledButton.icon(
                  onPressed: _submitting ? null : _submit,
                  icon: const Icon(Icons.send_rounded, size: 19),
                  label: const Text('Submit report'),
                ),
              ),
            ],
          ),
        ),
      ),
      body: Form(
        key: _form,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(
            Insets.lg,
            Insets.md,
            Insets.lg,
            Insets.xxl,
          ),
          children: <Widget>[
            _visitCard(theme),
            const SizedBox(height: Insets.md),
            _reportCard(theme),
            const SizedBox(height: Insets.md),
            _productCard(theme),
            const SizedBox(height: Insets.md),
            _extrasCard(theme),
            const SizedBox(height: Insets.lg),
            _attachedCard(theme),
            const SizedBox(height: Insets.lg),
            const AlertBanner(
              icon: Icons.save_outlined,
              title: 'Offline safe',
              message:
                  'If you are offline the report is stored as a draft and uploaded '
                  'automatically when the connection returns.',
              tone: AppColors.info,
            ),
          ],
        ),
      ),
    );
  }

  // ------------------------------------------------------- section 1: visit

  Widget _visitCard(ThemeData theme) {
    final List<CompanyVisit> todayVisits = MockData.todayVisits;
    final String typed = _company.text.trim();

    return CollapsibleCard(
      icon: Icons.storefront_outlined,
      title: 'Where you visited',
      subtitle: 'Type the shop name — it does not have to be on any list.',
      summary: typed.isEmpty
          ? 'Not set yet'
          : _branch.text.trim().isEmpty
              ? typed
              : '$typed · ${_branch.text.trim()}',
      complete: typed.length >= 2,
      expanded: _open.contains(_Section.visit),
      onToggle: () => _toggle(_Section.visit),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          TextFormField(
            controller: _company,
            textCapitalization: TextCapitalization.words,
            textInputAction: TextInputAction.next,
            onChanged: (_) {
              _unlinkVisit();
              setState(() {}); // keeps the collapsed summary honest
            },
            decoration: const InputDecoration(
              labelText: 'Company / shop name',
              hintText: 'e.g. Mohakhali EV Point',
              prefixIcon: Icon(Icons.business_outlined),
            ),
            validator: (String? v) => (v == null || v.trim().length < 2)
                ? 'Enter the company or shop name'
                : null,
          ),
          const SizedBox(height: Insets.md),
          TextFormField(
            controller: _branch,
            textCapitalization: TextCapitalization.words,
            onChanged: (_) {
              _unlinkVisit();
              setState(() {});
            },
            decoration: const InputDecoration(
              labelText: 'Branch / area (optional)',
              hintText: 'e.g. Wireless Gate',
              prefixIcon: Icon(Icons.store_mall_directory_outlined),
            ),
          ),
          if (_companyId != null) ...<Widget>[
            const SizedBox(height: Insets.md),
            Row(
              children: <Widget>[
                const Icon(Icons.link_rounded,
                    size: 15, color: AppColors.success),
                const SizedBox(width: Insets.xs),
                Expanded(
                  child: Text(
                    'Linked to a detected visit — this report will show on the '
                    'route map.',
                    style: theme.textTheme.bodySmall
                        ?.copyWith(color: AppColors.success),
                  ),
                ),
              ],
            ),
          ],
          if (todayVisits.isNotEmpty) ...<Widget>[
            const SizedBox(height: Insets.lg),
            Text('Fill from a detected visit', style: theme.textTheme.labelLarge),
            const SizedBox(height: 2),
            Text(
              'Optional. A shop can receive several reports per day.',
              style: theme.textTheme.bodySmall,
            ),
            const SizedBox(height: Insets.sm),
            Wrap(
              spacing: Insets.sm,
              runSpacing: Insets.sm,
              children: <Widget>[
                ChoiceChip(
                  label: const Text('No visit'),
                  selected: _visitId == null,
                  onSelected: (_) => _unlinkVisit(),
                ),
                ...todayVisits.map(
                  (CompanyVisit v) => ChoiceChip(
                    label: Text(
                      '${MockData.companyName(v.companyId)} · '
                      '${Fmt.time(v.arrival)}',
                    ),
                    selected: _visitId == v.id,
                    onSelected: (_) => _linkVisit(v),
                  ),
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }

  // ------------------------------------------------------ section 2: report

  Widget _reportCard(ThemeData theme) {
    final String title = _title.text.trim();

    return CollapsibleCard(
      icon: Icons.edit_note_rounded,
      title: 'Report',
      subtitle: 'What happened on the visit, plus photos.',
      summary: title.isEmpty
          ? '$_images photo${_images == 1 ? '' : 's'} · no title yet'
          : '$title · $_images photo${_images == 1 ? '' : 's'}',
      complete: title.length >= 4 && _body.text.trim().length >= 10,
      expanded: _open.contains(_Section.report),
      onToggle: () => _toggle(_Section.report),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          TextFormField(
            controller: _title,
            textCapitalization: TextCapitalization.sentences,
            onChanged: (_) => setState(() {}),
            decoration: const InputDecoration(
              labelText: 'Report title',
              hintText: 'e.g. Walk-in dealer — first order',
              prefixIcon: Icon(Icons.title_rounded),
            ),
            validator: (String? v) =>
                (v == null || v.trim().length < 4) ? 'Add a short title' : null,
          ),
          const SizedBox(height: Insets.lg),
          Text('Details', style: theme.textTheme.labelLarge),
          const SizedBox(height: Insets.sm),
          TextFormField(
            controller: _body,
            minLines: 5,
            maxLines: 10,
            textCapitalization: TextCapitalization.sentences,
            onChanged: (_) => setState(() {}),
            decoration: const InputDecoration(
              hintText:
                  'What was discussed, what was ordered, what needs follow-up…',
              alignLabelWithHint: true,
            ),
            validator: (String? v) => (v == null || v.trim().length < 10)
                ? 'Describe the visit in a sentence or two'
                : null,
          ),
          const SizedBox(height: Insets.lg),
          Row(
            children: <Widget>[
              Text('Photos', style: theme.textTheme.labelLarge),
              const Spacer(),
              Text('$_images added', style: theme.textTheme.bodySmall),
            ],
          ),
          const SizedBox(height: Insets.sm),
          Wrap(
            spacing: Insets.sm,
            runSpacing: Insets.sm,
            children: <Widget>[
              ...List<Widget>.generate(
                _images,
                (int i) => ReportImageTile(
                  index: i,
                  onRemove: () => setState(() => _images -= 1),
                ),
              ),
              _AddImageTile(onTap: _pickImage),
            ],
          ),
        ],
      ),
    );
  }

  // --------------------------------------------- section 3: products & sale

  Widget _productCard(ThemeData theme) {
    final List<Product> categoryProducts = _catalog;
    final Set<String> selectedInCategory = _lines
        .where((ProductSaleLine l) => l.category == _category)
        .map((ProductSaleLine l) => l.productId)
        .toSet();

    return CollapsibleCard(
      icon: Icons.electric_moped_rounded,
      title: 'Products & sale',
      subtitle: 'Pick a category, then the models you sold.',
      summary: _lines.isEmpty
          ? 'Nothing logged'
          : '${_lines.length} model${_lines.length == 1 ? '' : 's'} · '
              '$_unitsSold unit${_unitsSold == 1 ? '' : 's'}'
              '${_payment.text.trim().isEmpty ? '' : ' · ${_payment.text.trim()}'}',
      complete: _lines.isNotEmpty,
      expanded: _open.contains(_Section.products),
      onToggle: () => _toggle(_Section.products),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          SelectField<ProductCategory>(
            label: 'Category',
            hint: 'Select a category',
            sheetTitle: 'Product category',
            icon: Icons.category_outlined,
            value: _category,
            options: ProductCategory.values
                .map(
                  (ProductCategory c) => SelectOption<ProductCategory>(
                    value: c,
                    label: c.label,
                  ),
                )
                .toList(growable: false),
            onChanged: (ProductCategory? v) {
              if (v == null) return;
              _loadCatalog(v);
            },
          ),
          const SizedBox(height: Insets.lg),

          if (_category == null)
            Text(
              'Choose a category to load the model list.',
              style: theme.textTheme.bodySmall,
            )
          else if (_loadingCatalog)
            const LoadingCards(count: 1)
          else if (_catalogError != null)
            ErrorState(
              message: 'Could not load the ${_category!.label} list.',
              onRetry: () => _loadCatalog(_category!),
            )
          else
            MultiSelectField<String>(
              label: '${_category!.label} models',
              hint: 'Select the models you sold',
              sheetTitle: '${_category!.label} — select models',
              searchHint: 'Search model or code',
              icon: categoryIcon(_category!),
              values: selectedInCategory,
              chipLabel: (String id) => _known[id]?.name ?? id,
              options: categoryProducts
                  .map(
                    (Product p) => MultiSelectOption<String>(
                      value: p.id,
                      label: p.name,
                      subtitle: '${p.brand} · ${p.modelCode}',
                      trailing: p.rangeKm > 0 ? '${p.rangeKm} km' : null,
                    ),
                  )
                  .toList(growable: false),
              onChanged: _applySelection,
            ),

          if (_lines.isNotEmpty) ...<Widget>[
            const SizedBox(height: Insets.lg),
            Divider(height: 1, color: context.lineColor),
            const SizedBox(height: Insets.lg),
            Text('Selected models', style: theme.textTheme.labelLarge),
            const SizedBox(height: 2),
            Text(
              'Tap a photo for the full gallery and specs.',
              style: theme.textTheme.bodySmall,
            ),
            const SizedBox(height: Insets.md),
            ..._lines.asMap().entries.map((MapEntry<int, ProductSaleLine> e) {
              final Product? p = _known[e.value.productId];
              if (p == null) return const SizedBox.shrink();
              return Padding(
                padding: const EdgeInsets.only(bottom: Insets.md),
                child: ProductSaleCard(
                  product: p,
                  line: e.value,
                  onChanged: (ProductSaleLine updated) =>
                      setState(() => _lines[e.key] = updated),
                  onRemove: () => setState(() => _lines.removeAt(e.key)),
                ),
              );
            }),
            Container(
              padding: const EdgeInsets.all(Insets.md),
              decoration: BoxDecoration(
                color: AppColors.primary
                    .withValues(alpha: context.isDark ? 0.16 : 0.07),
                borderRadius: BorderRadius.circular(Radii.md),
              ),
              child: Row(
                children: <Widget>[
                  const Icon(Icons.shopping_bag_outlined,
                      size: 18, color: AppColors.primary),
                  const SizedBox(width: Insets.sm),
                  Expanded(
                    child: Text(
                      'Total units sold',
                      style: theme.textTheme.bodyLarge,
                    ),
                  ),
                  Text('$_unitsSold', style: theme.textTheme.titleLarge),
                ],
              ),
            ),
            const SizedBox(height: Insets.lg),
            TextFormField(
              controller: _payment,
              keyboardType: TextInputType.text,
              onChanged: (_) => setState(() {}),
              decoration: const InputDecoration(
                labelText: 'Payment received',
                hintText: 'e.g. BDT 90,000',
                prefixIcon: Icon(Icons.payments_outlined),
              ),
              validator: (String? v) {
                if (_lines.isEmpty) return null;
                return (v == null || v.trim().isEmpty)
                    ? 'Enter the amount collected (write 0 if none)'
                    : null;
              },
            ),
          ],
        ],
      ),
    );
  }

  // ------------------------------------------------------ section 4: extras

  Widget _extrasCard(ThemeData theme) {
    final String deal = _deal.text.trim();
    final List<String> bits = <String>[
      if (deal.isNotEmpty) deal,
      if (_followUp != null) 'follow-up ${Fmt.mediumDate(_followUp!)}',
    ];

    return CollapsibleCard(
      icon: Icons.event_repeat_outlined,
      title: 'Deal value & follow-up',
      subtitle: 'Both optional.',
      summary: bits.isEmpty ? 'None' : bits.join(' · '),
      complete: bits.isNotEmpty,
      tone: AppColors.info,
      expanded: _open.contains(_Section.extras),
      onToggle: () => _toggle(_Section.extras),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          TextFormField(
            controller: _deal,
            onChanged: (_) => setState(() {}),
            decoration: const InputDecoration(
              labelText: 'Deal value (optional)',
              hintText: 'e.g. BDT 2,55,000',
              prefixIcon: Icon(Icons.receipt_long_outlined),
            ),
          ),
          const SizedBox(height: Insets.md),
          AppCard(
            color: context.isDark
                ? AppColors.surfaceAltDark
                : AppColors.surfaceAlt,
            onTap: () async {
              final DateTime? picked = await showDatePicker(
                context: context,
                initialDate:
                    _followUp ?? DateTime.now().add(const Duration(days: 7)),
                firstDate: DateTime.now(),
                lastDate: DateTime.now().add(const Duration(days: 365)),
              );
              if (picked != null) setState(() => _followUp = picked);
            },
            child: Row(
              children: <Widget>[
                const Icon(Icons.event_repeat_outlined,
                    size: 19, color: AppColors.primary),
                const SizedBox(width: Insets.md),
                Expanded(
                  child: Text(
                    _followUp == null
                        ? 'Set a follow-up date (optional)'
                        : 'Follow-up · ${Fmt.mediumDate(_followUp!)}',
                    style: theme.textTheme.bodyLarge,
                  ),
                ),
                if (_followUp != null)
                  IconButton(
                    onPressed: () => setState(() => _followUp = null),
                    icon: const Icon(Icons.close_rounded, size: 18),
                  )
                else
                  const Icon(Icons.chevron_right_rounded, size: 20),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _attachedCard(ThemeData theme) {
    return AppCard(
      color: context.isDark ? AppColors.surfaceAltDark : AppColors.surfaceAlt,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              const Icon(Icons.link_rounded,
                  size: 17, color: AppColors.textSecondary),
              const SizedBox(width: Insets.sm),
              Text(
                'Attached automatically',
                style: theme.textTheme.titleMedium?.copyWith(fontSize: 13.5),
              ),
            ],
          ),
          const SizedBox(height: Insets.sm),
          KeyValueRow(
            label: 'Session',
            value: MockData.todaySessions.last.id,
            dense: true,
          ),
          KeyValueRow(
            label: 'Location',
            value: Fmt.latLng(
              MockData.lastFix.latitude,
              MockData.lastFix.longitude,
            ),
            dense: true,
          ),
          KeyValueRow(
            label: 'Timestamp',
            value: Fmt.time(DateTime.now()),
            dense: true,
          ),
        ],
      ),
    );
  }
}

enum ImageSourceChoice { camera, gallery }

class _AddImageTile extends StatelessWidget {
  const _AddImageTile({required this.onTap});

  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(Radii.md),
      child: Container(
        width: 88,
        height: 88,
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(Radii.md),
          border: Border.all(
            color: AppColors.primary.withValues(alpha: 0.4),
            style: BorderStyle.solid,
          ),
          color: AppColors.primary.withValues(alpha: 0.05),
        ),
        child: const Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: <Widget>[
            Icon(Icons.add_a_photo_outlined, color: AppColors.primary, size: 21),
            SizedBox(height: 5),
            Text(
              'Add photo',
              style: TextStyle(
                fontSize: 10.5,
                fontWeight: FontWeight.w600,
                color: AppColors.primary,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _SuccessView extends StatelessWidget {
  const _SuccessView({required this.onClose});

  final VoidCallback onClose;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(Insets.xxl),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: <Widget>[
              Container(
                width: 86,
                height: 86,
                decoration: const BoxDecoration(
                  color: AppColors.successSoft,
                  shape: BoxShape.circle,
                ),
                child: const Icon(
                  Icons.check_rounded,
                  color: AppColors.success,
                  size: 44,
                ),
              ),
              const SizedBox(height: Insets.xl),
              Text('Report submitted', style: theme.textTheme.headlineSmall),
              const SizedBox(height: Insets.sm),
              Text(
                'Linked to your session and current location, with the units and '
                'payment you logged. Your manager can see it right away.',
                textAlign: TextAlign.center,
                style: theme.textTheme.bodyMedium,
              ),
              const SizedBox(height: Insets.xxxl),
              SizedBox(
                width: double.infinity,
                height: Sizes.primaryActionHeight,
                child: FilledButton(
                  onPressed: onClose,
                  child: const Text('Done'),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
