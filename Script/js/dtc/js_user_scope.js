// js_user_scope.js — Kunci dropdown Line/Section ke area user login (project-wide).
// Admin/Management bebas semua. Operator terkunci ke line & section-nya.
// Supervisor multi-section (allowed_sections): opsi section dibatasi ke daftarnya.
(function () {
    function getScope() {
        var role = ((window.userRole || '').toLowerCase()).trim();
        var isAdmin = !!window.currentIsAdmin || role === 'admin' || role.indexOf('management') !== false;
        var line = ((window.userLineName || '').trim());
        var sections = [];
        var allowed = ((window.userAllowedSections || '').trim());
        if (allowed) {
            allowed.split(',').forEach(function (s) {
                s = s.trim();
                if (s) sections.push(s);
            });
        } else if (((window.userSectionName || '').trim())) {
            sections.push((window.userSectionName || '').trim());
        }
        return { isAdmin: isAdmin, line: line, sections: sections };
    }
    window.getUserScopeInfo = getScope;

    function ensureOption($sel, value) {
        if (!value) return;
        if ($sel.find('option').filter(function () { return $(this).val() === value; }).length === 0) {
            // Samakan label opsi filter ("All ..." vs "-- Select ...")
            var firstText = ($sel.find('option:first').text() || '');
            if (/all/i.test(firstText)) $sel.append(new Option(value, value));
            else $sel.append(new Option(value, value));
        }
    }

    function lockSelect($sel, value, scopeLabel) {
        if (!$sel || $sel.length === 0) return;
        ensureOption($sel, value);
        $sel.val(value);
        $sel.prop('disabled', true);
        $sel.attr('title', 'Terkunci ke area Anda (' + scopeLabel + ')');
        $sel.css('opacity', '0.75');
    }

    function pruneSections($sel, sections) {
        if (!$sel || $sel.length === 0) return;
        var cur = $sel.val();
        var lower = sections.map(function (s) { return s.toLowerCase(); });
        $sel.find('option').each(function () {
            var v = $(this).val();
            if (!v) return; // opsi "All ..." selalu dipertahankan
            if (lower.indexOf(v.toLowerCase()) === -1) $(this).remove();
        });
        sections.forEach(function (s) { ensureOption($sel, s); });
        if (cur && lower.indexOf(cur.toLowerCase()) !== -1) $sel.val(cur);
    }

    window.applyUserScopeToFilters = function () {
        var scope;
        try { scope = getScope(); } catch (e) { return; }
        if (!scope || scope.isAdmin) return;

        var lineSelectors = ['#filter-line', '#filter_line_name', '#rm_line_select', '#add_dtc_line', '#cs_line'];
        var sectionSelectors = ['#filter-section', '#filter_section_name', '#rm_section_select', '#add_dtc_section', '#cs_section'];

        if (scope.line) {
            lineSelectors.forEach(function (sel) {
                var $el = $(sel);
                if ($el.length) lockSelect($el, scope.line, scope.line);
            });
        }
        if (scope.sections.length === 1) {
            sectionSelectors.forEach(function (sel) {
                var $el = $(sel);
                if ($el.length) lockSelect($el, scope.sections[0], scope.sections[0]);
            });
        } else if (scope.sections.length > 1) {
            // Supervisor multi-section: batasi opsi, tetap bisa pilih
            sectionSelectors.forEach(function (sel) {
                pruneSections($(sel), scope.sections);
            });
        }

        var hint = scope.line + (scope.sections.length ? ' • ' + scope.sections.join(', ') : '');
        $('#dtc-filter-hint').text(hint ? ('Area: ' + hint) : '').toggle(!!hint);
    };

    $(document).ready(function () {
        try { window.applyUserScopeToFilters(); } catch (e) {}
        // Terapkan ulang setelah dropdown diisi via AJAX (idempotent)
        setTimeout(function () { try { window.applyUserScopeToFilters(); } catch (e) {} }, 900);
    });
    $(document).on('ajaxComplete', function () {
        try { window.applyUserScopeToFilters(); } catch (e) {}
    });
})();
