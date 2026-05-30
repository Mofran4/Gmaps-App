/**
 * Author: Bonifasius Mofran Abimanyu
 * Stack: PHP + jQuery + Mapbox GL JS
 */

$(function () {
    // CONFIG & STATE
    mapboxgl.accessToken = 'MAPBOX_TOKEN'; // defined in config.php

    const API = 'api/records.php';
    const CENTER = [107.6191, -6.9175]; // Bandung default center
    const CATEGORIES = {
        'Kuliner':      { icon: 'fa-utensils',       color: '#f7b731' },
        'Pendidikan':   { icon: 'fa-graduation-cap', color: '#4f8ef7' },
        'Pariwisata':   { icon: 'fa-mountain',       color: '#22c9a0' },
        'Kesehatan':    { icon: 'fa-hospital',        color: '#f25f5c' },
        'Hiburan':      { icon: 'fa-masks-theater',  color: '#e056fd' },
        'Perbelanjaan': { icon: 'fa-bag-shopping',   color: '#fd9644' },
        'Ibadah':       { icon: 'fa-place-of-worship', color: '#45aaf2' },
        'Olahraga':     { icon: 'fa-futbol',         color: '#26de81' },
        'Perkantoran':  { icon: 'fa-building',       color: '#a29bfe' },
        'Lainnya':      { icon: 'fa-location-dot',   color: '#8892b0' },
    };

    // State
    let state = {
        records: [],
        markers: {},
        activeFilter: 'all',
        activeRecordId: null,
        mapBounds: null,
        miniMap: null,
        miniMapCenter: CENTER,
        isEditing: false,
    };

    // MAIN MAP INIT
    const map = new mapboxgl.Map({
        container: 'map',
        style: 'mapbox://styles/mapbox/dark-v11',
        center: CENTER,
        zoom: 12,
    });

    map.addControl(new mapboxgl.NavigationControl(), 'top-right');
    map.addControl(new mapboxgl.FullscreenControl(), 'top-right');
    map.addControl(new mapboxgl.GeolocateControl({
        positionOptions: { enableHighAccuracy: true },
        trackUserLocation: true,
    }), 'top-right');

    // On map move end, update bounds filter and reload records
    let moveTimer;
    map.on('moveend', function () {
        clearTimeout(moveTimer);
        moveTimer = setTimeout(function () {
            const b = map.getBounds();
            state.mapBounds = `${b.getSouth()},${b.getWest()},${b.getNorth()},${b.getEast()}`;
            updateInfoBar();
            loadRecords(false);
        }, 300);
    });

    // MINI MAP INIT
    function initMiniMap() {
        if (state.miniMap) return;
        state.miniMap = new mapboxgl.Map({
            container: 'miniMap',
            style: 'mapbox://styles/mapbox/dark-v11',
            center: state.miniMapCenter,
            zoom: 12,
        });

        state.miniMap.on('move', function () {
            const c = state.miniMap.getCenter();
            $('#fLat').val(c.lat.toFixed(6));
            $('#fLng').val(c.lng.toFixed(6));
        });
    }

    // LOAD RECORDS FROM API
    function loadRecords(showLoading = true) {
        if (showLoading) {
            $('#recordList').html('<div class="loading-state"><i class="fa-solid fa-spinner fa-spin"></i><span>Memuat data...</span></div>');
        }

        const params = { action: 'list', category: state.activeFilter };
        if (state.mapBounds) params.bounds = state.mapBounds;

        $.get(API, params, function (res) {
            if (!res.success) return showToast('Gagal memuat data.', 'error');
            state.records = res.data;
            renderRecordList();
            renderMarkers();
            loadCategories();
        }, 'json').fail(function () {
            showToast('Gagal terhubung ke server.', 'error');
        });
    }

    // LOAD CATEGORIES
    function loadCategories() {
        $.get(API, { action: 'categories' }, function (res) {
            if (!res.success) return;
            const $chips = $('#filterChips');
            $chips.html('<button class="chip chip-all ' + (state.activeFilter === 'all' ? 'active' : '') + '" data-cat="all"><i class="fa-solid fa-layer-group"></i> Semua</button>');
            res.data.forEach(function (c) {
                const cfg = CATEGORIES[c.category] || CATEGORIES['Lainnya'];
                $chips.append(`
                    <button class="chip ${state.activeFilter === c.category ? 'active' : ''}" 
                            data-cat="${c.category}"
                            style="${state.activeFilter === c.category ? 'background:' + cfg.color + ';border-color:' + cfg.color : ''}">
                        <i class="fa-solid ${cfg.icon}"></i> ${c.category}
                        <span style="opacity:.7;margin-left:2px">${c.total}</span>
                    </button>`);
            });
        }, 'json');
    }

    // RENDER Sidebar
    function renderRecordList() {
        const $list = $('#recordList');
        $('#recordCount').text(state.records.length + ' record');

        if (state.records.length === 0) {
            $list.html('<div class="empty-state"><i class="fa-solid fa-map-location-dot"></i><span>Tidak ada record di area ini</span></div>');
            return;
        }

        let html = '';
        state.records.forEach(function (r) {
            const cfg = CATEGORIES[r.category] || CATEGORIES['Lainnya'];
            html += `
            <div class="record-item ${state.activeRecordId == r.id ? 'active' : ''}" data-id="${r.id}">
                <div class="rec-icon" style="background:${cfg.color}22; color:${cfg.color}">
                    <i class="fa-solid ${cfg.icon}"></i>
                </div>
                <div class="rec-info">
                    <div class="rec-title">${escHtml(r.title)}</div>
                    <div class="rec-addr"><i class="fa-solid fa-location-dot" style="font-size:10px"></i> ${escHtml(r.address)}</div>
                    <span class="rec-cat" style="background:${cfg.color}22; color:${cfg.color}">${escHtml(r.category)}</span>
                </div>
            </div>`;
        });
        $list.html(html);
    }

    // RENDER MAP MARKERS
    function renderMarkers() {
        // Remove existing markers
        Object.values(state.markers).forEach(function (m) { m.remove(); });
        state.markers = {};
        state.records.forEach(function (r) {
            const cfg = CATEGORIES[r.category] || CATEGORIES['Lainnya'];

            // Custom marker element
            const el = document.createElement('div');
            el.className = 'custom-marker';
            el.setAttribute('data-id', r.id);
            el.innerHTML = `<div class="marker-pin" style="background:${cfg.color}"><span class="marker-icon"><i class="fa-solid ${cfg.icon}"></i></span></div>`;
            el.addEventListener('click', function (e) {
                e.stopPropagation();
                flyToRecord(r);
                showInfoPopup(r);
                setActiveRecord(r.id);
            });

            const marker = new mapboxgl.Marker({ 
                element: el,
                rotationAlignment: 'viewport', 
                pitchAlignment: 'viewport'    
            })
            .setLngLat([r.longitude, r.latitude])
            .addTo(map);

            state.markers[r.id] = marker;
        });
    }

    // FLY TO RECORD ON MAP
    function flyToRecord(r) {
        map.flyTo({
            center: [r.longitude, r.latitude],
            zoom: Math.max(map.getZoom(), 14),
            speed: 1.4,
            curve: 1.2,
        });
    }

    // SHOW INFO POPUP
    function showInfoPopup(r) {
        state.activeRecordId = r.id;
        const cfg = CATEGORIES[r.category] || CATEGORIES['Lainnya'];
        $('#ipCat').html(`<i class="fa-solid ${cfg.icon}"></i> ${r.category}`).css('color', cfg.color);
        $('#ipTitle').text(r.title);
        $('#ipAddr').text(r.address);
        $('#ipDetail').text(r.detail);
        $('#btnEditRec').off('click').on('click', function () { openEditModal(r); });
        $('#btnDelRec').off('click').on('click', function () { deleteRecord(r.id); });
        $('#infoPopup').show();
    }

    $('#infoPopupClose').on('click', function () {
        $('#infoPopup').hide();
        setActiveRecord(null);
    });

    function setActiveRecord(id) {
        state.activeRecordId = id;
        $('.record-item').removeClass('active');
        if (id) $('.record-item[data-id="' + id + '"]').addClass('active');
    }

    // CLICK RECORD IN SIDEBAR
    $(document).on('click', '.record-item', function () {
        const id = $(this).data('id');
        const r = state.records.find(function (x) { return x.id == id; });
        if (!r) return;
        flyToRecord(r);
        showInfoPopup(r);
        setActiveRecord(r.id);
    });

    // FILTER BY CATEGORY
    $(document).on('click', '.chip', function () {
        state.activeFilter = $(this).data('cat');
        state.mapBounds = null; // reset bounds filter when changing category
        loadRecords();
    });

    // OPEN ADD/EDIT MODAL
    function openAddModal() {
        state.isEditing = false;
        $('#modalTitleText').text('Tambah Lokasi Baru');
        $('#recordForm')[0].reset();
        $('#recordId').val('');

        // Set mini map center to current main map center
        const c = map.getCenter();
        state.miniMapCenter = [c.lng, c.lat];
        $('#modalOverlay').addClass('active');
        setTimeout(function () {
            initMiniMap();
            state.miniMap.setCenter(state.miniMapCenter);
            state.miniMap.resize();
            // Sync lat/lng fields with mini map center
            $('#fLat').val(c.lat.toFixed(6));
            $('#fLng').val(c.lng.toFixed(6));
        }, 100);
    }

    function openEditModal(r) {
        state.isEditing = true;
        $('#modalTitleText').text('Edit Lokasi');
        $('#recordId').val(r.id);
        $('#fTitle').val(r.title);
        $('#fAddress').val(r.address);
        $('#fDetail').val(r.detail);
        $('#fCategory').val(r.category);
        $('#fLat').val(r.latitude);
        $('#fLng').val(r.longitude);

        state.miniMapCenter = [r.longitude, r.latitude];
        $('#modalOverlay').addClass('active');

        setTimeout(function () {
            initMiniMap();
            state.miniMap.setCenter([r.longitude, r.latitude]);
            state.miniMap.resize();
        }, 100);
    }

    function closeModal() {
        $('#modalOverlay').removeClass('active');
    }

    $('#btnAddRecord').on('click', openAddModal);
    $('#modalClose, #btnCancel').on('click', closeModal);
    $('#modalOverlay').on('click', function (e) {
        if ($(e.target).is('#modalOverlay')) closeModal();
    });

    // SYNC MINI MAP CENTER WITH LAT/LNG FIELDS
    $('#fLat, #fLng').on('input', function () {
        const lat = parseFloat($('#fLat').val());
        const lng = parseFloat($('#fLng').val());
        if (!isNaN(lat) && !isNaN(lng) && state.miniMap) {
            state.miniMap.setCenter([lng, lat]);
        }
    });

    // SAVE RECORD (CREATE OR UPDATE)
    $('#btnSave').on('click', function () {
        const data = {
            id:       $('#recordId').val(),
            title:    $('#fTitle').val().trim(),
            address:  $('#fAddress').val().trim(),
            detail:   $('#fDetail').val().trim(),
            category: $('#fCategory').val(),
            latitude: parseFloat($('#fLat').val()),
            longitude: parseFloat($('#fLng').val()),
        };

        if (!data.title || !data.address || !data.detail || !data.category || isNaN(data.latitude) || isNaN(data.longitude)) {
            showToast('Mohon lengkapi semua field yang wajib diisi.', 'error');
            return;
        }

        const action = state.isEditing ? 'update' : 'create';
        $('#btnSave').prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Menyimpan...');

        $.post(API + '?action=' + action, JSON.stringify(data), function (res) {
            if (res.success) {
                showToast(res.message, 'success');
                closeModal();
                loadRecords();
                setTimeout(function () {
                    map.flyTo({ center: [data.longitude, data.latitude], zoom: 15, speed: 1.4 });
                }, 300);
            } else {
                showToast(res.message || 'Gagal menyimpan.', 'error');
            }
        }, 'json')
        .fail(function () { showToast('Gagal terhubung ke server.', 'error'); })
        .always(function () {
            $('#btnSave').prop('disabled', false).html('<i class="fa-solid fa-floppy-disk"></i> Simpan Record');
        });
    });

    // DELETE RECORD
    function deleteRecord(id) {
        if (!confirm('Yakin ingin menghapus record ini?')) return;
        $.post(API + '?action=delete', JSON.stringify({ id: id }), function (res) {
            if (res.success) {
                showToast(res.message, 'success');
                $('#infoPopup').hide();
                setActiveRecord(null);
                loadRecords();
            } else {
                showToast(res.message || 'Gagal menghapus.', 'error');
            }
        }, 'json');
    }

    // UPDATE INFO BAR WITH CURRENT RECORD COUNT IN VIEWPORT
    function updateInfoBar() {
        const b = map.getBounds();
        const count = state.records.length;
        $('#mapInfoText').text(`Menampilkan ${count} lokasi di area ini`);
    }

    // TOAST NOTIFICATION
    let toastTimer;
    function showToast(msg, type) {
        clearTimeout(toastTimer);
        const icons = { success: 'fa-circle-check', error: 'fa-circle-xmark', info: 'fa-circle-info' };
        const $t = $('#toast');
        $t.attr('class', 'toast ' + (type || 'info'));
        $t.html(`<i class="fa-solid ${icons[type] || 'fa-circle-info'}"></i> ${msg}`);
        $t.addClass('show');
        toastTimer = setTimeout(function () { $t.removeClass('show'); }, 3000);
    }

    // UTILS
    function escHtml(str) {
        return $('<div>').text(str).html();
    }

    // INITIAL LOAD
    map.on('load', function () {
        loadRecords(true);
    });

});