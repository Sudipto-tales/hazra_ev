import 'dart:async';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../../core/theme/app_colors.dart';
import '../../core/theme/dimens.dart';
import '../../core/theme/theme_ext.dart';
import '../../core/utils/formatters.dart';
import '../../data/company_directory.dart';
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
/// Session, GPS and timestamp are attached silently — read from the real day
/// (`GET /days/me`), not assumed.
///
/// Photos are picked from the camera or the gallery and uploaded in a **second
/// request** after the report has an id, which is how the server models it. A
/// failure there is an attachment failure, never a lost report.
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

  // Empty at construction. The names behind `widget.visit`'s ids are not known
  // until the company directory has loaded, so the prefill happens in
  // `_loadDay` rather than in a field initialiser that would have to guess.
  final TextEditingController _company = TextEditingController();
  final TextEditingController _branch = TextEditingController();
  final TextEditingController _title = TextEditingController();
  final TextEditingController _body = TextEditingController();
  final TextEditingController _deal = TextEditingController();
  final TextEditingController _payment = TextEditingController();

  /// Kept only while the typed names still match the linked visit.
  late String? _companyId = widget.visit?.companyId;
  late String? _branchId = widget.visit?.branchId;
  late String? _visitId = widget.visit?.id;

  DateTime? _followUp;

  /// Today, as the server sees it: the visits offered as links, and the
  /// session + last fix the report is silently attached to.
  HomeSnapshot? _day;
  bool _dayFailed = false;

  final Set<_Section> _open = <_Section>{_Section.visit, _Section.report};

  // ------------------------------------------------------------ attachments

  final ImagePicker _picker = ImagePicker();
  final List<ReportAttachment> _images = <ReportAttachment>[];

  /// `imageQuality` re-encodes to JPEG, `maxWidth` caps the width. Between
  /// them a 12 MP phone frame lands comfortably under the server's 8 MB limit
  /// — which is the point: finding out a photo is too big *after* the seller
  /// has tapped Submit is the worst possible moment for it.
  static const int _jpegQuality = 85;
  static const double _maxWidth = 2400;

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
  bool _done = false;

  /// What the submit is currently doing, for the progress label. There is no
  /// byte-level progress to report — a multipart body is one request — so the
  /// honest thing to show is the stage, not a made-up percentage.
  String _stage = '';

  /// Set once `POST /reports` has returned. From then on the report exists
  /// server-side and a retry must never file it a second time.
  String? _reportId;

  /// Set when the report was filed but its pictures were not.
  String? _attachmentError;

  int get _unitsSold =>
      _lines.fold<int>(0, (int sum, ProductSaleLine l) => sum + l.units);

  @override
  void initState() {
    super.initState();
    // Needs an AppScope, so it cannot run before the first frame.
    WidgetsBinding.instance.addPostFrameCallback((_) => _loadDay());
  }

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

  /// Three things the form was not handed: the names behind `widget.visit`'s
  /// ids, today's other visits to offer as links, and the session + last fix
  /// that ride along silently.
  ///
  /// None of them is worth blocking on. A seller standing in a shop with no
  /// signal still has to be able to file the report, so a failure here leaves
  /// the labels honest ("Not available") and the form fully usable.
  Future<void> _loadDay() async {
    final AppScope scope = AppScope.of(context);

    // Swallows its own errors and degrades to empty — see CompanyDirectory.
    await scope.companies.ensureLoaded();
    if (!mounted) return;
    setState(_prefillFromVisit);

    try {
      final HomeSnapshot day = await scope.repository.home();
      if (!mounted) return;
      setState(() => _day = day);
    } catch (_) {
      if (!mounted) return;
      setState(() => _dayFailed = true);
    }
  }

  /// Fills the two text fields from the visit the form was opened with.
  ///
  /// Only with what the directory actually knows: an unknown id resolves to
  /// null, and an empty field the seller types into is a far better outcome
  /// than a different company's name sitting in it.
  void _prefillFromVisit() {
    final CompanyVisit? visit = widget.visit;
    if (visit == null) return;

    final CompanyDirectory directory = AppScope.of(context).companies;

    if (_company.text.isEmpty) {
      _company.text = directory.companyName(visit.companyId) ?? '';
    }
    if (_branch.text.isEmpty) {
      _branch.text =
          directory.branchName(visit.companyId, visit.branchId) ?? '';
    }
  }

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
    final CompanyDirectory directory = AppScope.of(context).companies;

    setState(() {
      _visitId = v.id;
      _companyId = v.companyId;
      _branchId = v.branchId;
      // Keep whatever is typed when the directory cannot name the company —
      // the link is still correct, only the label is unknown.
      _company.text = directory.companyName(v.companyId) ?? _company.text;
      _branch.text = directory.branchName(v.companyId, v.branchId) ?? '';
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
    if (choice == null || !mounted) return;

    final List<XFile> picked = <XFile>[];

    try {
      if (choice == ImageSourceChoice.camera) {
        final XFile? shot = await _picker.pickImage(
          source: ImageSource.camera,
          imageQuality: _jpegQuality,
          maxWidth: _maxWidth,
        );
        if (shot != null) picked.add(shot);
      } else {
        picked.addAll(
          await _picker.pickMultiImage(
            imageQuality: _jpegQuality,
            maxWidth: _maxWidth,
          ),
        );
      }
    } catch (_) {
      // Permission refused, no camera, picker cancelled by the OS. Nothing to
      // recover — say so and leave the form as it was.
      if (!mounted) return;
      _toast(
        choice == ImageSourceChoice.camera
            ? 'Could not open the camera. Check the app permission.'
            : 'Could not open the gallery. Check the app permission.',
      );
      return;
    }

    if (picked.isEmpty || !mounted) return;

    final List<ReportAttachment> accepted = <ReportAttachment>[];
    final List<String> rejected = <String>[];

    for (final XFile file in picked) {
      final ReportAttachment attachment = ReportAttachment(
        path: file.path,
        name: file.name,
        byteSize: await file.length(),
      );

      // The server enforces the same two rules and answers 422. Catching them
      // here means the seller finds out while they are still looking at the
      // picker, not after the report has already gone.
      final String? why = attachment.rejection;
      if (why != null) {
        rejected.add(why);
        continue;
      }

      accepted.add(attachment);
    }

    if (!mounted) return;

    if (accepted.isNotEmpty) {
      setState(() => _images.addAll(accepted));
    }

    if (rejected.isNotEmpty) {
      _toast(rejected.join('\n'));
    }
  }

  void _toast(String message) {
    ScaffoldMessenger.of(context)
        .showSnackBar(SnackBar(content: Text(message)));
  }

  /// A `TextFormField` inside a collapsed section is not mounted, so the `Form`
  /// cannot see it. Open every section and wait one frame before validating.
  Future<void> _openEverything() {
    setState(() => _open.addAll(_Section.values));
    final Completer<void> settled = Completer<void>();
    WidgetsBinding.instance.addPostFrameCallback((_) => settled.complete());
    return settled.future;
  }

  /// Two calls, and only the first one can lose anything.
  ///
  /// `POST /reports` files the text. Once it returns an id the report exists on
  /// the server for good, so [_reportId] is the latch: a retry after that point
  /// re-sends the pictures only and never files a second report. A failure in
  /// the second call is surfaced as an attachment problem, because that is what
  /// it is.
  Future<void> _submit() async {
    await _openEverything();
    if (!mounted) return;
    if (!(_form.currentState?.validate() ?? false)) return;

    final EmployeeRepository repository = AppScope.of(context).repository;

    setState(() {
      _submitting = true;
      _done = false;
      _attachmentError = null;
      _stage = 'Submitting report…';
    });

    if (_reportId == null) {
      try {
        final VisitReport filed = await repository.submitReport(
          ReportDraft(
            companyName: _company.text.trim(),
            branchName:
                _branch.text.trim().isEmpty ? null : _branch.text.trim(),
            companyId: _companyId,
            branchId: _branchId,
            title: _title.text.trim(),
            body: _body.text.trim(),
            images: List<ReportAttachment>.unmodifiable(_images),
            visitId: _visitId,
            dealValue: _deal.text.trim().isEmpty ? null : _deal.text.trim(),
            followUpOn: _followUp,
            sales: List<ProductSaleLine>.unmodifiable(_lines),
            paymentReceived:
                _payment.text.trim().isEmpty ? null : _payment.text.trim(),
          ),
        );
        _reportId = filed.id;
      } catch (_) {
        if (!mounted) return;
        setState(() => _submitting = false);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content:
                const Text('Upload failed — saved as a draft on this device'),
            action: SnackBarAction(label: 'Retry', onPressed: _submit),
          ),
        );
        return;
      }
    }

    if (!mounted) return;

    if (_images.isEmpty) {
      setState(() {
        _submitting = false;
        _done = true;
      });
      return;
    }

    // Snapshot: `_images` is still editable while this runs, and the list the
    // upload streams from must not change underneath it.
    final List<ReportAttachment> batch = List<ReportAttachment>.of(_images);

    setState(() => _stage = 'Uploading ${_photoCount(batch.length)}…');

    try {
      final List<ReportImage> stored =
          await repository.uploadReportImages(_reportId!, batch);
      if (!mounted) return;

      // The server writes each part as it validates it and stops at the first
      // one it refuses, so what comes back is the prefix of `batch` that made
      // it. Drop exactly those: a retry must not upload the same picture twice.
      for (int i = 0; i < stored.length && i < batch.length; i++) {
        _images.remove(batch[i]);
      }

      final int missing = batch.length - stored.length;

      setState(() {
        _submitting = false;
        _done = true;
        _attachmentError = missing <= 0
            ? null
            : '${_photoCount(missing)} did not upload.';
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _submitting = false;
        _done = true;
        _attachmentError = 'Your photos did not upload.';
      });
    }
  }

  static String _photoCount(int n) => '$n photo${n == 1 ? '' : 's'}';

  void _toggle(_Section s) => setState(() {
        if (!_open.remove(s)) _open.add(s);
      });

  // ------------------------------------------------------------------- build

  @override
  Widget build(BuildContext context) {
    if (_done) {
      return _SuccessView(
        attachmentError: _attachmentError,
        // Retrying sends only the pictures the server has not confirmed, and
        // never the report again — see the latch in `_submit`.
        onRetryAttachments: _attachmentError == null || _images.isEmpty
            ? null
            : _submit,
        onClose: () => Navigator.pop(context),
      );
    }

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
                // Indeterminate on purpose. A multipart body is one request
                // with no progress events, and the old animated percentage was
                // a number the app made up.
                ClipRRect(
                  borderRadius: BorderRadius.circular(Radii.pill),
                  child: const LinearProgressIndicator(minHeight: 6),
                ),
                const SizedBox(height: Insets.sm),
                Text(_stage, style: theme.textTheme.bodySmall),
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
    final CompanyDirectory directory = AppScope.of(context).companies;
    final List<CompanyVisit> todayVisits = _day?.visits ?? const <CompanyVisit>[];
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
                      // Never another company's name for an id the directory
                      // does not know — that is what displayCompany is for.
                      '${directory.displayCompany(v.companyId)} · '
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
    final String photos = _photoCount(_images.length);

    return CollapsibleCard(
      icon: Icons.edit_note_rounded,
      title: 'Report',
      subtitle: 'What happened on the visit, plus photos.',
      summary: title.isEmpty ? '$photos · no title yet' : '$title · $photos',
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
              Text(
                _images.isEmpty ? 'None yet' : '${_images.length} added',
                style: theme.textTheme.bodySmall,
              ),
            ],
          ),
          const SizedBox(height: 2),
          Text(
            'JPEG, PNG or WebP, up to 8 MB each. They upload after the report '
            'is filed.',
            style: theme.textTheme.bodySmall,
          ),
          const SizedBox(height: Insets.sm),
          Wrap(
            spacing: Insets.sm,
            runSpacing: Insets.sm,
            children: <Widget>[
              ..._images.asMap().entries.map(
                    (MapEntry<int, ReportAttachment> e) => ReportImageTile.file(
                      attachment: e.value,
                      // Locked mid-upload: the batch on the wire is already
                      // fixed, so removing one here would only desync the list.
                      onRemove: _submitting
                          ? null
                          : () => setState(() => _images.removeAt(e.key)),
                    ),
                  ),
              _AddImageTile(onTap: _submitting ? null : _pickImage),
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

  /// What the server will staple to the report. Read from the real day, so
  /// when it is not known yet the row says so instead of showing a fixture.
  Widget _attachedCard(ThemeData theme) {
    final HomeSnapshot? day = _day;

    // The open session is the one the report belongs to. Falling back to the
    // last closed one covers writing up a visit right after clocking out.
    final WorkSession? session = day == null
        ? null
        : day.activeSession ?? (day.sessions.isEmpty ? null : day.sessions.last);

    final LocationLog? fix = day?.lastFix;

    final String unknown = _dayFailed ? 'Not available' : 'Loading…';

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
            value: session?.id ?? (day == null ? unknown : 'No session today'),
            dense: true,
          ),
          KeyValueRow(
            label: 'Location',
            value: fix == null
                ? (day == null ? unknown : 'No fix yet')
                : Fmt.latLng(fix.latitude, fix.longitude),
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

  /// Null while a submit is in flight.
  final VoidCallback? onTap;

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

/// The report is filed either way — that is the one thing this screen is
/// allowed to claim. [attachmentError] is the honest footnote when the second
/// request did not land: the manager can read the report now, the photos are
/// still on the phone, and Retry sends only those.
class _SuccessView extends StatelessWidget {
  const _SuccessView({
    required this.onClose,
    this.attachmentError,
    this.onRetryAttachments,
  });

  final VoidCallback onClose;
  final String? attachmentError;
  final VoidCallback? onRetryAttachments;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final String? problem = attachmentError;

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
              if (problem != null) ...<Widget>[
                const SizedBox(height: Insets.xl),
                AlertBanner(
                  icon: Icons.image_not_supported_outlined,
                  tone: AppColors.warning,
                  title: 'Photos not attached',
                  message: '$problem The report itself is safely filed — only '
                      'the pictures need another try.',
                  actionLabel:
                      onRetryAttachments == null ? null : 'Retry photos',
                  onAction: onRetryAttachments,
                ),
              ],
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
