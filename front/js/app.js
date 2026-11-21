/* ScrumBoard UB · front end del taller de microservicios (JavaScript puro).
 * Toda la comunicación se hace con fetch() hacia el API Gateway (/api/...). */
(function () {
    'use strict';

    const $app = document.getElementById('app');
    const ESTADOS = ['nueva', 'activa', 'finalizada', 'impedimento'];
    const PRIORIDADES = ['alta', 'media', 'baja'];
    const PUNTOS = [1, 2, 3, 5, 8, 13, 21];
    const AVANZAR = { nueva: 'Iniciar', activa: 'Finalizar', impedimento: 'Reanudar' };

    // Estado de pantalla (formularios abiertos y filtros)
    const estado = { sprintForm: null, histForm: null, filtros: { sprint: '0', estado: '', q: '' }, informeSprint: '0' };

    // ---------- Utilidades ----------
    const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g,
        (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const pct = (a, b) => (b > 0 ? Math.round((a * 100) / b) : 0);
    const hoy = () => {
        const d = new Date();
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    };
    const sumarDias = (n) => {
        const d = new Date(); d.setDate(d.getDate() + n);
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    };

    function aviso(tipo, mensaje) {
        const div = document.createElement('div');
        div.className = 'alert alert--' + tipo;
        div.textContent = mensaje;
        document.getElementById('avisos').appendChild(div);
        setTimeout(() => div.remove(), 5000);
    }

    /** Llama al gateway. Lanza Error con .errores si el servicio responde con fallo. */
    async function api(ruta, opciones) {
        opciones = opciones || {};
        let r;
        try {
            r = await fetch('/api' + ruta, {
                method: opciones.method || 'GET',
                headers: { 'Content-Type': 'application/json' },
                body: opciones.body ? JSON.stringify(opciones.body) : undefined,
            });
        } catch (e) {
            throw new Error('No se pudo conectar con el gateway. ¿Está encendido?');
        }
        let datos = null;
        try { datos = await r.json(); } catch (e) { /* sin cuerpo JSON */ }
        if (!r.ok) {
            const err = new Error((datos && datos.error) || 'Error ' + r.status);
            err.errores = (datos && datos.errores) || {};
            throw err;
        }
        return datos;
    }

    // ---------- Piezas de HTML ----------
    const badge = (valor) => `<span class="badge badge--${esc(valor)}">${esc(valor)}</span>`;
    const barra = (p, extra) => `<div class="bar ${extra || ''}"><span style="width:${p}%"></span></div> <small>${p}%</small>`;

    function campo(nombre, etiqueta, valor, errores, opciones) {
        opciones = opciones || {};
        const tipo = opciones.tipo || 'text';
        const clase = 'input' + (errores[nombre] ? ' is-invalid' : '');
        const error = errores[nombre] ? `<small class="error">${esc(errores[nombre])}</small>` : '';
        let control;
        if (tipo === 'select') {
            control = `<select class="${clase}" id="f-${nombre}" name="${nombre}">${opciones.opciones.map(
                (o) => `<option value="${esc(o[0])}" ${String(o[0]) === String(valor) ? 'selected' : ''}>${esc(o[1])}</option>`).join('')}</select>`;
        } else if (tipo === 'textarea') {
            control = `<textarea class="${clase}" id="f-${nombre}" name="${nombre}" rows="3" placeholder="${esc(opciones.placeholder || '')}">${esc(valor)}</textarea>`;
        } else {
            control = `<input class="${clase}" id="f-${nombre}" name="${nombre}" type="${tipo}" value="${esc(valor)}" placeholder="${esc(opciones.placeholder || '')}">`;
        }
        return `<div class="field ${opciones.ancho ? 'field--wide' : ''}"><label for="f-${nombre}">${etiqueta}</label>${control}${error}</div>`;
    }

    // ---------- Vista: Inicio ----------
    async function vistaInicio() {
        const [gen, imp] = await Promise.all([api('/informes/general'), api('/historias?estado=impedimento')]);
        const nombres = {};
        gen.sprints.forEach((s) => { nombres[s.id] = s.nombre; });
        const r = gen.resumen;

        $app.innerHTML = `
            <div class="page-head">
                <h1>Panel principal</h1>
                <div class="actions">
                    <a class="btn btn--ghost" href="#/sprints" data-accion="nuevo-sprint">+ Nuevo sprint</a>
                    <a class="btn btn--primary" href="#/historias" data-accion="nueva-historia">+ Nueva historia</a>
                </div>
            </div>
            <section class="stats">
                <article class="stat"><span class="stat__n">${gen.sprints.length}</span><span class="stat__l">Sprints</span></article>
                <article class="stat"><span class="stat__n">${r.historias}</span><span class="stat__l">Historias</span></article>
                <article class="stat"><span class="stat__n">${r.puntos}</span><span class="stat__l">Puntos totales</span></article>
                <article class="stat"><span class="stat__n">${r.avance}%</span><span class="stat__l">Avance (puntos finalizados)</span></article>
                <article class="stat"><span class="stat__n">${gen.por_estado.impedimento.historias}</span><span class="stat__l">Con impedimento</span></article>
            </section>
            <section class="card">
                <h2>Avance por sprint</h2>
                ${gen.sprints.length ? `<div class="table-wrap"><table class="table">
                    <thead><tr><th>Sprint</th><th>Periodo</th><th>Historias</th><th>Puntos</th><th style="width:28%">Avance</th></tr></thead>
                    <tbody>${gen.sprints.map((s) => `<tr>
                        <td><strong>${esc(s.nombre)}</strong><br><span class="muted">${esc(s.objetivo)}</span></td>
                        <td>${esc(s.fecha_inicio)} → ${esc(s.fecha_fin)}</td>
                        <td>${s.historias}</td><td>${s.puntos_finalizados} / ${s.puntos}</td>
                        <td>${barra(s.avance)}</td></tr>`).join('')}</tbody></table></div>`
                    : '<p class="muted">Aún no hay sprints. Crea el primero para empezar.</p>'}
            </section>
            <section class="card">
                <h2>Historias con impedimento</h2>
                ${imp.length ? `<ul class="lista">${imp.map((h) => `<li><strong>${esc(h.titulo)}</strong>
                    <span class="muted">· ${esc(nombres[h.sprint_id] || 'Sprint ' + h.sprint_id)} · ${esc(h.responsable)}</span></li>`).join('')}</ul>`
                    : '<p class="muted">No hay historias con impedimento.</p>'}
            </section>`;
    }

    // ---------- Vista: Sprints ----------
    function formSprint(f) {
        const v = f.valores, e = f.errores;
        return `<form class="card form" data-form="sprint" novalidate>
            <h2>${v.id ? 'Editar sprint' : 'Nuevo sprint'}</h2>
            <div class="grid">
                ${campo('nombre', 'Nombre', v.nombre, e, { placeholder: 'Sprint 3' })}
                ${campo('objetivo', 'Objetivo del sprint', v.objetivo, e, { ancho: true, placeholder: '¿Qué se quiere lograr?' })}
                ${campo('fecha_inicio', 'Fecha de inicio', v.fecha_inicio, e, { tipo: 'date' })}
                ${campo('fecha_fin', 'Fecha de fin', v.fecha_fin, e, { tipo: 'date' })}
            </div>
            <div class="form__actions">
                <button class="btn btn--primary" type="submit">Guardar</button>
                <button class="btn btn--ghost" type="button" data-accion="cancelar">Cancelar</button>
            </div>
        </form>`;
    }

    async function vistaSprints() {
        const [sprints, gen] = await Promise.all([api('/sprints'), api('/informes/general').catch(() => null)]);
        const met = {};
        if (gen) { gen.sprints.forEach((s) => { met[s.id] = s; }); }

        $app.innerHTML = `
            <div class="page-head"><h1>Sprints</h1>
                <button class="btn btn--primary" data-accion="nuevo-sprint">+ Nuevo sprint</button></div>
            ${estado.sprintForm ? formSprint(estado.sprintForm) : ''}
            ${gen ? '' : '<div class="alert alert--error">El servicio de informes no responde: no se muestran las métricas de avance.</div>'}
            <div class="card"><div class="table-wrap"><table class="table">
                <thead><tr><th>Sprint</th><th>Periodo</th><th>Historias</th><th>Puntos</th><th style="width:20%">Avance</th><th class="right">Acciones</th></tr></thead>
                <tbody>${sprints.map((s) => {
                    const m = met[s.id];
                    return `<tr>
                        <td><strong>${esc(s.nombre)}</strong><br><span class="muted">${esc(s.objetivo)}</span></td>
                        <td>${esc(s.fecha_inicio)} → ${esc(s.fecha_fin)}</td>
                        <td>${m ? m.historias : '—'}</td>
                        <td>${m ? m.puntos_finalizados + ' / ' + m.puntos : '—'}</td>
                        <td>${m ? barra(m.avance) : '—'}</td>
                        <td class="right actions">
                            <button class="btn btn--small" data-accion="informe-sprint" data-id="${s.id}">Informe</button>
                            <button class="btn btn--small btn--ghost" data-accion="editar-sprint" data-id="${s.id}">Editar</button>
                            <button class="btn btn--small btn--danger" data-accion="eliminar-sprint" data-id="${s.id}" data-nombre="${esc(s.nombre)}">Eliminar</button>
                        </td></tr>`;
                }).join('') || '<tr><td colspan="6" class="muted">No hay sprints registrados.</td></tr>'}</tbody>
            </table></div></div>`;
    }

    // ---------- Vista: Historias ----------
    function formHistoria(f, sprints) {
        const v = f.valores, e = f.errores;
        return `<form class="card form" data-form="historia" novalidate>
            <h2>${v.id ? 'Editar historia' : 'Nueva historia'}</h2>
            <div class="grid">
                ${campo('titulo', 'Título', v.titulo, e, { ancho: true })}
                ${campo('descripcion', 'Descripción', v.descripcion, e, { tipo: 'textarea', ancho: true, placeholder: 'Como [rol] quiero [acción] para [beneficio]' })}
                ${campo('responsable', 'Responsable', v.responsable, e)}
                ${campo('sprint_id', 'Sprint', v.sprint_id, e, { tipo: 'select', opciones: [['', '— Selecciona —']].concat(sprints.map((s) => [s.id, s.nombre])) })}
                ${campo('prioridad', 'Prioridad', v.prioridad, e, { tipo: 'select', opciones: PRIORIDADES.map((p) => [p, p[0].toUpperCase() + p.slice(1)]) })}
                ${campo('estado', 'Estado', v.estado, e, { tipo: 'select', opciones: ESTADOS.map((s) => [s, s[0].toUpperCase() + s.slice(1)]) })}
                ${campo('puntos', 'Puntos de historia', v.puntos, e, { tipo: 'select', opciones: PUNTOS.map((p) => [p, p]) })}
                ${campo('fecha_creacion', 'Fecha de creación', v.fecha_creacion, e, { tipo: 'date' })}
            </div>
            <p class="muted">La fecha de finalización se registra sola cuando la historia pasa a «finalizada».</p>
            <div class="form__actions">
                <button class="btn btn--primary" type="submit">Guardar</button>
                <button class="btn btn--ghost" type="button" data-accion="cancelar">Cancelar</button>
            </div>
        </form>`;
    }

    async function vistaHistorias() {
        const f = estado.filtros;
        const consulta = [];
        if (f.sprint !== '0') { consulta.push('sprint_id=' + encodeURIComponent(f.sprint)); }
        if (f.estado) { consulta.push('estado=' + encodeURIComponent(f.estado)); }
        const [historias, sprints] = await Promise.all([
            api('/historias' + (consulta.length ? '?' + consulta.join('&') : '')), api('/sprints')]);
        const nombres = {};
        sprints.forEach((s) => { nombres[s.id] = s.nombre; });

        $app.innerHTML = `
            <div class="page-head"><h1>Historias de usuario</h1>
                <button class="btn btn--primary" data-accion="nueva-historia">+ Nueva historia</button></div>
            ${estado.histForm ? formHistoria(estado.histForm, sprints) : ''}
            <div class="card">
                <div class="filtros">
                    <select class="input" data-filtro="sprint" aria-label="Filtrar por sprint">
                        <option value="0">Todos los sprints</option>
                        ${sprints.map((s) => `<option value="${s.id}" ${String(s.id) === f.sprint ? 'selected' : ''}>${esc(s.nombre)}</option>`).join('')}
                    </select>
                    <select class="input" data-filtro="estado" aria-label="Filtrar por estado">
                        <option value="">Todos los estados</option>
                        ${ESTADOS.map((e) => `<option value="${e}" ${e === f.estado ? 'selected' : ''}>${e[0].toUpperCase() + e.slice(1)}</option>`).join('')}
                    </select>
                    <input type="search" class="input input--search" id="buscar" placeholder="Buscar por título, responsable…" value="${esc(f.q)}" aria-label="Buscar historias">
                </div>
                <div class="table-wrap"><table class="table" id="tabla-historias">
                    <thead><tr><th>Historia</th><th>Sprint</th><th>Responsable</th><th>Prioridad</th><th>Pts</th><th>Estado</th><th class="right">Acciones</th></tr></thead>
                    <tbody>${historias.map((h) => `<tr>
                        <td><strong>${esc(h.titulo)}</strong><br><span class="muted">${esc(h.descripcion.length > 70 ? h.descripcion.slice(0, 70) + '…' : h.descripcion)}</span></td>
                        <td>${esc(nombres[h.sprint_id] || 'Sprint ' + h.sprint_id)}</td>
                        <td>${esc(h.responsable)}</td>
                        <td>${badge(h.prioridad)}</td><td>${h.puntos}</td><td>${badge(h.estado)}</td>
                        <td class="right actions">
                            ${AVANZAR[h.estado] ? `<button class="btn btn--small" data-accion="avanzar-historia" data-id="${h.id}">${AVANZAR[h.estado]}</button>` : ''}
                            <button class="btn btn--small btn--ghost" data-accion="editar-historia" data-id="${h.id}">Editar</button>
                            <button class="btn btn--small btn--danger" data-accion="eliminar-historia" data-id="${h.id}" data-nombre="${esc(h.titulo)}">Eliminar</button>
                        </td></tr>`).join('') || '<tr><td colspan="7" class="muted">No hay historias con ese filtro.</td></tr>'}</tbody>
                </table></div>
            </div>`;
        aplicarBusqueda();
    }

    function aplicarBusqueda() {
        const texto = estado.filtros.q.trim().toLowerCase();
        document.querySelectorAll('#tabla-historias tbody tr').forEach((fila) => {
            fila.style.display = fila.textContent.toLowerCase().indexOf(texto) === -1 ? 'none' : '';
        });
    }

    // ---------- Vista: Informes ----------
    async function vistaInformes() {
        const id = estado.informeSprint;
        const [sprints, inf] = await Promise.all([
            api('/sprints'), api(id === '0' ? '/informes/general' : '/informes/sprint/' + id)]);
        const r = inf.resumen;

        $app.innerHTML = `
            <div class="page-head"><h1>Informes</h1>
                <div class="filtros"><select class="input" data-informe aria-label="Sprint del informe">
                    <option value="0">Todos los sprints</option>
                    ${sprints.map((s) => `<option value="${s.id}" ${String(s.id) === id ? 'selected' : ''}>${esc(s.nombre)}</option>`).join('')}
                </select></div></div>
            <section class="stats">
                <article class="stat"><span class="stat__n">${r.historias}</span><span class="stat__l">Historias</span></article>
                <article class="stat"><span class="stat__n">${r.puntos}</span><span class="stat__l">Puntos totales</span></article>
                <article class="stat"><span class="stat__n">${r.puntos_finalizados}</span><span class="stat__l">Puntos finalizados</span></article>
                <article class="stat"><span class="stat__n">${r.avance}%</span><span class="stat__l">Avance</span></article>
            </section>
            <section class="card"><h2>Historias por estado</h2><div class="table-wrap"><table class="table">
                <thead><tr><th>Estado</th><th>Historias</th><th>Puntos</th><th style="width:40%">Proporción</th></tr></thead>
                <tbody>${ESTADOS.map((e) => { const v = inf.por_estado[e]; return `<tr>
                    <td>${badge(e)}</td><td>${v.historias}</td><td>${v.puntos}</td>
                    <td>${barra(pct(v.historias, r.historias), 'bar--' + e)}</td></tr>`; }).join('')}</tbody>
            </table></div></section>
            <section class="card"><h2>Carga de trabajo por responsable</h2>
                ${inf.por_responsable.length ? `<div class="table-wrap"><table class="table">
                    <thead><tr><th>Responsable</th><th>Total</th><th>Nuevas</th><th>Activas</th><th>Finalizadas</th><th>Impedimentos</th><th>Puntos</th></tr></thead>
                    <tbody>${inf.por_responsable.map((x) => `<tr><td><strong>${esc(x.responsable)}</strong></td>
                        <td>${x.total}</td><td>${x.nuevas}</td><td>${x.activas}</td><td>${x.finalizadas}</td><td>${x.impedimentos}</td>
                        <td>${x.puntos_finalizados} / ${x.puntos}</td></tr>`).join('')}</tbody></table></div>`
                    : '<p class="muted">No hay historias para mostrar.</p>'}
            </section>`;
    }

    // ---------- Enrutamiento ----------
    const vistas = { inicio: vistaInicio, sprints: vistaSprints, historias: vistaHistorias, informes: vistaInformes };

    async function navegar() {
        const ruta = (location.hash || '#/inicio').replace(/^#\//, '').split('?')[0];
        const nombre = vistas[ruta] ? ruta : 'inicio';
        document.querySelectorAll('#menu a').forEach((a) => a.classList.toggle('is-active', a.dataset.ruta === nombre));
        try {
            await vistas[nombre]();
        } catch (e) {
            $app.innerHTML = `<div class="alert alert--error">${esc(e.message)}</div>
                <p class="muted">Comprueba que los cuatro servicios estén encendidos (ver pie de página).</p>`;
        }
    }

    function irA(ruta) {
        if (location.hash === '#/' + ruta) { navegar(); } else { location.hash = '#/' + ruta; }
    }

    // ---------- Eventos (delegación) ----------
    document.addEventListener('click', async (ev) => {
        const el = ev.target.closest('[data-accion]');
        if (!el) { return; }
        if (el.tagName === 'A') { ev.preventDefault(); }
        const accion = el.dataset.accion, id = el.dataset.id;
        try {
            if (accion === 'nuevo-sprint') {
                estado.sprintForm = { valores: { nombre: '', objetivo: '', fecha_inicio: hoy(), fecha_fin: sumarDias(14) }, errores: {} };
                irA('sprints');
            } else if (accion === 'editar-sprint') {
                estado.sprintForm = { valores: await api('/sprints/' + id), errores: {} };
                navegar();
            } else if (accion === 'eliminar-sprint') {
                if (!confirm('¿Eliminar ' + el.dataset.nombre + '?')) { return; }
                await api('/sprints/' + id, { method: 'DELETE' });
                aviso('ok', 'Sprint eliminado.');
                navegar();
            } else if (accion === 'informe-sprint') {
                estado.informeSprint = id;
                irA('informes');
            } else if (accion === 'nueva-historia') {
                estado.histForm = { valores: { titulo: '', descripcion: '', responsable: '', prioridad: 'media', estado: 'nueva',
                    puntos: 3, fecha_creacion: hoy(), sprint_id: estado.filtros.sprint !== '0' ? estado.filtros.sprint : '' }, errores: {} };
                irA('historias');
            } else if (accion === 'editar-historia') {
                estado.histForm = { valores: await api('/historias/' + id), errores: {} };
                navegar();
            } else if (accion === 'avanzar-historia') {
                await api('/historias/' + id + '/avanzar', { method: 'POST' });
                aviso('ok', 'Estado de la historia actualizado.');
                navegar();
            } else if (accion === 'eliminar-historia') {
                if (!confirm('¿Eliminar la historia «' + el.dataset.nombre + '»?')) { return; }
                await api('/historias/' + id, { method: 'DELETE' });
                aviso('ok', 'Historia eliminada.');
                navegar();
            } else if (accion === 'cancelar') {
                estado.sprintForm = null; estado.histForm = null;
                navegar();
            }
        } catch (e) {
            aviso('error', e.message);
        }
    });

    document.addEventListener('submit', async (ev) => {
        const form = ev.target.closest('form[data-form]');
        if (!form) { return; }
        ev.preventDefault();
        const tipo = form.dataset.form;                       // 'sprint' | 'historia'
        const datos = Object.fromEntries(new FormData(form).entries());
        const actual = tipo === 'sprint' ? estado.sprintForm : estado.histForm;
        const id = actual.valores.id;
        const base = tipo === 'sprint' ? '/sprints' : '/historias';
        if (tipo === 'historia') { datos.puntos = Number(datos.puntos); }

        try {
            await api(id ? base + '/' + id : base, { method: id ? 'PUT' : 'POST', body: datos });
            aviso('ok', tipo === 'sprint' ? 'Sprint guardado correctamente.' : 'Historia guardada correctamente.');
            if (tipo === 'sprint') { estado.sprintForm = null; } else { estado.histForm = null; }
        } catch (e) {
            actual.valores = Object.assign({}, actual.valores, datos);
            actual.errores = e.errores || {};
            aviso('error', Object.keys(actual.errores).length ? 'Revisa los campos marcados.' : e.message);
        }
        navegar();
    });

    document.addEventListener('change', (ev) => {
        const t = ev.target;
        if (t.dataset.filtro) { estado.filtros[t.dataset.filtro] = t.value; navegar(); }
        if (t.hasAttribute('data-informe')) { estado.informeSprint = t.value; navegar(); }
    });

    document.addEventListener('input', (ev) => {
        if (ev.target.id === 'buscar') { estado.filtros.q = ev.target.value; aplicarBusqueda(); }
    });

    // ---------- Estado de los microservicios (pie de página) ----------
    async function revisarSalud() {
        await Promise.all(['sprints', 'historias', 'informes'].map(async (s) => {
            let ok = false;
            try { ok = (await fetch('/api/' + s + '/health')).ok; } catch (e) { ok = false; }
            const punto = document.querySelector('#salud [data-servicio="' + s + '"]');
            if (punto) { punto.className = ok ? 'on' : 'off'; }
        }));
    }

    window.addEventListener('hashchange', navegar);
    navegar();
    revisarSalud();
    setInterval(revisarSalud, 10000);
})();
