--
-- PostgreSQL database dump
--

\restrict 0qQFha0fNtfs6YjNPxNe2KcqJQInYBO8ymaiRQSqWXiozCFeeE8E40Le94ZlB6Z

-- Dumped from database version 18.6
-- Dumped by pg_dump version 18.6

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: cache; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache (
    key character varying(255) NOT NULL,
    value text NOT NULL,
    expiration integer NOT NULL
);


--
-- Name: cache_locks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache_locks (
    key character varying(255) NOT NULL,
    owner character varying(255) NOT NULL,
    expiration integer NOT NULL
);


--
-- Name: cat_estatus_fase; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cat_estatus_fase (
    id integer NOT NULL,
    nombre character varying(50) NOT NULL,
    orden integer DEFAULT 0 NOT NULL,
    es_completada boolean DEFAULT false NOT NULL,
    es_rechazada boolean DEFAULT false NOT NULL,
    en_reproceso boolean DEFAULT false NOT NULL,
    bloquea_captura boolean DEFAULT false NOT NULL
);


--
-- Name: cat_estatus_fase_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.cat_estatus_fase_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: cat_estatus_fase_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.cat_estatus_fase_id_seq OWNED BY public.cat_estatus_fase.id;


--
-- Name: cat_estatus_pieza; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cat_estatus_pieza (
    id integer NOT NULL,
    nombre character varying(50) NOT NULL
);


--
-- Name: cat_estatus_pieza_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.cat_estatus_pieza_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: cat_estatus_pieza_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.cat_estatus_pieza_id_seq OWNED BY public.cat_estatus_pieza.id;


--
-- Name: cat_fases; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cat_fases (
    id integer NOT NULL,
    nombre character varying(50) NOT NULL,
    orden_secuencia integer NOT NULL
);


--
-- Name: cat_fases_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.cat_fases_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: cat_fases_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.cat_fases_id_seq OWNED BY public.cat_fases.id;


--
-- Name: cat_roles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cat_roles (
    id integer NOT NULL,
    nombre character varying(50) NOT NULL,
    descripcion character varying(255)
);


--
-- Name: cat_roles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.cat_roles_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: cat_roles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.cat_roles_id_seq OWNED BY public.cat_roles.id;


--
-- Name: cat_tipos_estructura; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cat_tipos_estructura (
    id integer NOT NULL,
    nombre character varying(50) NOT NULL
);


--
-- Name: cat_tipos_estructura_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.cat_tipos_estructura_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: cat_tipos_estructura_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.cat_tipos_estructura_id_seq OWNED BY public.cat_tipos_estructura.id;


--
-- Name: evidencias_fotograficas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.evidencias_fotograficas (
    id integer NOT NULL,
    observacion_calidad_id integer,
    ruta_archivo character varying(255) NOT NULL,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    fase_pieza_id bigint
);


--
-- Name: evidencias_fotograficas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.evidencias_fotograficas_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: evidencias_fotograficas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.evidencias_fotograficas_id_seq OWNED BY public.evidencias_fotograficas.id;


--
-- Name: failed_jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.failed_jobs (
    id bigint NOT NULL,
    uuid character varying(255) NOT NULL,
    connection text NOT NULL,
    queue text NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.failed_jobs_id_seq OWNED BY public.failed_jobs.id;


--
-- Name: fases_pieza; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.fases_pieza (
    id integer NOT NULL,
    pieza_id integer NOT NULL,
    fase_id integer NOT NULL,
    operario_id integer,
    usuario_captura_id integer NOT NULL,
    fecha_inicio timestamp without time zone,
    fecha_termino timestamp without time zone,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    estatus_fase_id integer NOT NULL,
    fecha_dictamen timestamp without time zone
);


--
-- Name: fases_pieza_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.fases_pieza_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: fases_pieza_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.fases_pieza_id_seq OWNED BY public.fases_pieza.id;


--
-- Name: job_batches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.job_batches (
    id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    total_jobs integer NOT NULL,
    pending_jobs integer NOT NULL,
    failed_jobs integer NOT NULL,
    failed_job_ids text NOT NULL,
    options text,
    cancelled_at integer,
    created_at integer NOT NULL,
    finished_at integer
);


--
-- Name: jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.jobs (
    id bigint NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    attempts smallint NOT NULL,
    reserved_at integer,
    available_at integer NOT NULL,
    created_at integer NOT NULL
);


--
-- Name: jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.jobs_id_seq OWNED BY public.jobs.id;


--
-- Name: logs_auditoria; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.logs_auditoria (
    id integer NOT NULL,
    usuario_id integer,
    accion character varying(100) NOT NULL,
    tabla_afectada character varying(50) NOT NULL,
    registro_id integer,
    datos_previos jsonb,
    datos_nuevos jsonb,
    fecha_hora timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: logs_auditoria_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.logs_auditoria_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: logs_auditoria_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.logs_auditoria_id_seq OWNED BY public.logs_auditoria.id;


--
-- Name: obras; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.obras (
    id integer NOT NULL,
    codigo_obra character varying(30) NOT NULL,
    nombre_obra character varying(150) NOT NULL,
    cliente character varying(150) NOT NULL,
    fecha_inicio date NOT NULL,
    fecha_compromiso date NOT NULL,
    estatus character varying(20) DEFAULT 'Activa'::character varying,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    cliente_usuario_id integer
);


--
-- Name: obras_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.obras_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: obras_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.obras_id_seq OWNED BY public.obras.id;


--
-- Name: observaciones_calidad; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.observaciones_calidad (
    id integer NOT NULL,
    fase_pieza_id integer NOT NULL,
    usuario_id integer NOT NULL,
    descripcion text NOT NULL,
    severidad character varying(20) DEFAULT 'Media'::character varying,
    corregido boolean DEFAULT false,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    resultado character varying(50),
    editado boolean DEFAULT false NOT NULL,
    editado_at timestamp without time zone,
    editado_por integer
);


--
-- Name: observaciones_calidad_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.observaciones_calidad_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: observaciones_calidad_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.observaciones_calidad_id_seq OWNED BY public.observaciones_calidad.id;


--
-- Name: operario_fase; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.operario_fase (
    id integer NOT NULL,
    operario_id integer NOT NULL,
    fase_id integer NOT NULL
);


--
-- Name: operario_fase_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.operario_fase_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: operario_fase_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.operario_fase_id_seq OWNED BY public.operario_fase.id;


--
-- Name: operarios; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.operarios (
    id integer NOT NULL,
    clave_empleado character varying(20) NOT NULL,
    nombre character varying(100) NOT NULL,
    apellido character varying(100) NOT NULL,
    especialidad character varying(50),
    activo boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: operarios_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.operarios_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: operarios_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.operarios_id_seq OWNED BY public.operarios.id;


--
-- Name: piezas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.piezas (
    id integer NOT NULL,
    obra_id integer NOT NULL,
    tipo_estructura_id integer NOT NULL,
    estatus_id integer NOT NULL,
    clave_marca_pieza character varying(50) NOT NULL,
    peso_kg numeric(10,2) NOT NULL,
    cantidad integer DEFAULT 1,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: piezas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.piezas_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: piezas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.piezas_id_seq OWNED BY public.piezas.id;


--
-- Name: sessions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sessions (
    id character varying(255) NOT NULL,
    user_id bigint,
    ip_address character varying(45),
    user_agent text,
    payload text NOT NULL,
    last_activity integer NOT NULL
);


--
-- Name: usuarios; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.usuarios (
    id integer NOT NULL,
    rol_id integer,
    nombre character varying(100) NOT NULL,
    email character varying(150) NOT NULL,
    password_hash character varying(255) NOT NULL,
    activo boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    codigo_acceso character varying(30)
);


--
-- Name: usuarios_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.usuarios_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: usuarios_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.usuarios_id_seq OWNED BY public.usuarios.id;


--
-- Name: cat_estatus_fase id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cat_estatus_fase ALTER COLUMN id SET DEFAULT nextval('public.cat_estatus_fase_id_seq'::regclass);


--
-- Name: cat_estatus_pieza id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cat_estatus_pieza ALTER COLUMN id SET DEFAULT nextval('public.cat_estatus_pieza_id_seq'::regclass);


--
-- Name: cat_fases id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cat_fases ALTER COLUMN id SET DEFAULT nextval('public.cat_fases_id_seq'::regclass);


--
-- Name: cat_roles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cat_roles ALTER COLUMN id SET DEFAULT nextval('public.cat_roles_id_seq'::regclass);


--
-- Name: cat_tipos_estructura id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cat_tipos_estructura ALTER COLUMN id SET DEFAULT nextval('public.cat_tipos_estructura_id_seq'::regclass);


--
-- Name: evidencias_fotograficas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.evidencias_fotograficas ALTER COLUMN id SET DEFAULT nextval('public.evidencias_fotograficas_id_seq'::regclass);


--
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);


--
-- Name: fases_pieza id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fases_pieza ALTER COLUMN id SET DEFAULT nextval('public.fases_pieza_id_seq'::regclass);


--
-- Name: jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);


--
-- Name: logs_auditoria id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.logs_auditoria ALTER COLUMN id SET DEFAULT nextval('public.logs_auditoria_id_seq'::regclass);


--
-- Name: obras id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.obras ALTER COLUMN id SET DEFAULT nextval('public.obras_id_seq'::regclass);


--
-- Name: observaciones_calidad id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.observaciones_calidad ALTER COLUMN id SET DEFAULT nextval('public.observaciones_calidad_id_seq'::regclass);


--
-- Name: operarios id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.operarios ALTER COLUMN id SET DEFAULT nextval('public.operarios_id_seq'::regclass);


--
-- Name: piezas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.piezas ALTER COLUMN id SET DEFAULT nextval('public.piezas_id_seq'::regclass);


--
-- Name: usuarios id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usuarios ALTER COLUMN id SET DEFAULT nextval('public.usuarios_id_seq'::regclass);


--
-- Data for Name: cache; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.cache (key, value, expiration) FROM stdin;
\.


--
-- Data for Name: cache_locks; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.cache_locks (key, owner, expiration) FROM stdin;
\.


--
-- Data for Name: cat_estatus_fase; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.cat_estatus_fase (id, nombre, orden, es_completada, es_rechazada, en_reproceso, bloquea_captura) FROM stdin;
1	En Proceso	1	f	f	f	f
2	En Reproceso	2	f	f	t	f
3	Rechazada	3	f	t	f	t
4	Aprobada por Calidad	4	f	f	f	f
5	Completada	5	t	f	f	t
\.


--
-- Data for Name: cat_estatus_pieza; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.cat_estatus_pieza (id, nombre) FROM stdin;
1	Pendiente
2	En Proceso
3	En Revisión
4	Detenida
5	Aprobada
\.


--
-- Data for Name: cat_fases; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.cat_fases (id, nombre, orden_secuencia) FROM stdin;
1	Armado	1
2	Soldadura	2
3	Pintura	3
\.


--
-- Data for Name: cat_roles; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.cat_roles (id, nombre, descripcion) FROM stdin;
1	Jefe de Producción	Administración general de obras, asignación y dashboards
2	Jefe de Calidad	Validación de fases, inspección física y registro de hallazgos
3	Visitante / Cliente	\N
\.


--
-- Data for Name: cat_tipos_estructura; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.cat_tipos_estructura (id, nombre) FROM stdin;
1	Pesada
2	Semipesada
3	Ligera
\.


--
-- Data for Name: evidencias_fotograficas; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.evidencias_fotograficas (id, observacion_calidad_id, ruta_archivo, created_at, fase_pieza_id) FROM stdin;
3	\N	evidencias/4XOxuHBCFRokbj8LoX9uVPOnh35b6VZJioqLGKii.jpg	2026-09-08 17:50:42.374149	1
4	\N	evidencias/tttKLn6QG54vA6cNBqyEkAeKeqvv64F20kByIXxg.jpg	2026-09-08 17:52:29.291951	1
5	\N	evidencias/tox6DV3O8MhWvUgMrptQnL3Ty6NC4W0o2u1qo7Sw.jpg	2026-09-08 17:54:19.440715	1
6	9	evidencias/ZEsDJxg0AmhdJH2a1MheTBj7y9RJUxmwukp3qUHs.webp	2026-09-09 17:19:37.364777	1
\.


--
-- Data for Name: failed_jobs; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.failed_jobs (id, uuid, connection, queue, payload, exception, failed_at) FROM stdin;
\.


--
-- Data for Name: fases_pieza; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.fases_pieza (id, pieza_id, fase_id, operario_id, usuario_captura_id, fecha_inicio, fecha_termino, created_at, estatus_fase_id, fecha_dictamen) FROM stdin;
10	2	2	1	1	2026-09-27 23:59:10	2026-09-28 00:24:47	2026-09-27 23:59:10.08283	5	2026-09-28 00:23:16
23	2	3	1	1	2026-09-28 00:25:07	\N	2026-09-28 00:25:07.324933	2	2026-09-28 00:25:58
1	1	1	1	1	2026-09-07 23:56:07	2026-09-23 12:00:00	2026-09-07 23:56:07.334833	5	2026-09-23 20:17:02
2	1	2	1	1	2026-09-23 20:18:57	2026-09-23 12:00:00	2026-09-23 20:18:57.448646	5	2026-09-23 20:53:26
5	2	1	1	1	2026-09-27 23:21:15	2026-09-27 23:58:58	2026-09-27 23:21:15.779904	5	2026-09-27 23:57:48
4	1	3	1	1	2026-09-24 18:00:59	2026-09-28 00:06:21	2026-09-24 18:00:59.766675	5	2026-09-24 18:15:22
\.


--
-- Data for Name: job_batches; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.job_batches (id, name, total_jobs, pending_jobs, failed_jobs, failed_job_ids, options, cancelled_at, created_at, finished_at) FROM stdin;
\.


--
-- Data for Name: jobs; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.jobs (id, queue, payload, attempts, reserved_at, available_at, created_at) FROM stdin;
\.


--
-- Data for Name: logs_auditoria; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.logs_auditoria (id, usuario_id, accion, tabla_afectada, registro_id, datos_previos, datos_nuevos, fecha_hora) FROM stdin;
\.


--
-- Data for Name: obras; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.obras (id, codigo_obra, nombre_obra, cliente, fecha_inicio, fecha_compromiso, estatus, created_at, cliente_usuario_id) FROM stdin;
1	OB-2026-001	Nave Industrial TechPark	Construcciones del Norte S.A.	2026-03-01	2026-06-30	Activa	2026-09-02 02:59:25.748496	12
\.


--
-- Data for Name: observaciones_calidad; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.observaciones_calidad (id, fase_pieza_id, usuario_id, descripcion, severidad, corregido, created_at, resultado, editado, editado_at, editado_por) FROM stdin;
12	1	2	eljiwebfrehjrfe	Media	f	2026-09-10 16:33:04.760093	Enviado a Reproceso	f	\N	\N
13	1	2	jrdtrhdtrjtycjhgjgfhh	Media	f	2026-09-10 16:44:11.736934	Enviado a Reproceso	f	\N	\N
14	1	2	htdtrdtrdtrdtrdtrdr	Media	f	2026-09-14 17:56:40.968866	Enviado a Reproceso	f	\N	\N
15	1	2	mcvjgfccgfcgfh	Media	f	2026-09-21 16:31:30.642174	Enviado a Reproceso	f	\N	\N
16	1	2	jhvgvhgvhgv	Media	f	2026-09-23 20:17:02.533193	Aprobado	f	\N	\N
17	2	2	gcgfcgcgfcfcc	Media	f	2026-09-23 20:53:26.555216	Aprobado	f	\N	\N
18	4	2	wexhcjvbcghjfxdzsffxg	Media	f	2026-09-24 18:15:22.565626	Aprobado	f	\N	\N
4	1	2	prueba	Media	f	2026-09-08 00:13:59.120697	Enviado a Reproceso	f	\N	\N
5	1	1	jgufyufyuy	Media	f	2026-09-08 00:15:13.191622	Enviado a Reproceso	f	\N	\N
6	1	2	qbfhubsbfh	Media	f	2026-09-08 18:09:21.430632	Enviado a Reproceso	f	\N	\N
7	1	2	sdfsdfsd	Media	f	2026-09-08 18:09:51.05813	Enviado a Reproceso	f	\N	\N
9	1	2	jnlinlininniln	Media	f	2026-09-09 17:19:36.089581	Enviado a Reproceso	f	\N	\N
10	1	2	mjxchtrxrthr	Media	f	2026-09-09 17:40:45.450763	Enviado a Reproceso	f	\N	\N
11	1	2	ydvvhgdhgfdvhvgf	Media	f	2026-09-10 16:23:58.416549	Enviado a Reproceso	f	\N	\N
28	5	2	jyfytdtrdtrdtrdc	Media	f	2026-09-27 23:57:48.598332	Aprobado	f	\N	\N
29	10	2	jtrdtrdtrydtrdtrd	Media	f	2026-09-28 00:00:16.565354	Aprobado	f	\N	\N
30	10	2	gvjhhvhvvjvgh	Media	f	2026-09-28 00:00:31.188284	Aprobado	f	\N	\N
51	10	2	bcbkdjhbdjhbjhbjbsdhjfbjhbdsjhfbjhbfdshj	Media	f	2026-09-28 00:23:16.88734	Aprobado	f	\N	\N
52	23	2	uuyfsbuhhbjhsdbhjbfds	Media	f	2026-09-28 00:25:58.969424	Enviado a Reproceso	f	\N	\N
\.


--
-- Data for Name: operario_fase; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.operario_fase (id, operario_id, fase_id) FROM stdin;
1	1	3
2	1	1
3	1	2
\.


--
-- Data for Name: operarios; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.operarios (id, clave_empleado, nombre, apellido, especialidad, activo, created_at) FROM stdin;
1	OP-001	Juan	Pérez	Armador Técnico	t	2026-09-02 02:58:54.492796
\.


--
-- Data for Name: piezas; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.piezas (id, obra_id, tipo_estructura_id, estatus_id, clave_marca_pieza, peso_kg, cantidad, created_at) FROM stdin;
2	1	1	3	TB2	416846.00	1	2026-09-27 23:20:34.861223
1	1	1	5	TB1	450.50	4	2026-09-02 02:59:40.536193
\.


--
-- Data for Name: sessions; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.sessions (id, user_id, ip_address, user_agent, payload, last_activity) FROM stdin;
MqyHUvkaoTszQjyqsuBkI2UOul1aXuFN7MPOZGwb	\N	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36	eyJfdG9rZW4iOiIzNWNEbk1lM0VUakQwemwwOW1XQUEyTzdGbWpRcWdBSDRzcXhianhpIiwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119LCJfcHJldmlvdXMiOnsidXJsIjoiaHR0cDpcL1wvMTI3LjAuMC4xOjgwMDBcL2xvZ2luIiwicm91dGUiOiJsb2dpbiJ9fQ==	1791311135
0VRGyud13xtjN08Xs0Dfoxwu6VGrn3GQluK0CWJq	\N	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36	eyJfdG9rZW4iOiJLMDRHWjlRdWh0OGVFYzZFVzM1T3RtSFI2TXg3ejY1MHQ1WWRTMW5TIiwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119LCJfcHJldmlvdXMiOnsidXJsIjoiaHR0cDpcL1wvMTI3LjAuMC4xOjgwMDBcL2NvbnN1bHRhXC9GQU1FUC1aT0tFXC9vYnJhXC8xIiwicm91dGUiOiJjb25zdWx0YS5vYnJhIn19	1791473225
\.


--
-- Data for Name: usuarios; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.usuarios (id, rol_id, nombre, email, password_hash, activo, created_at, codigo_acceso) FROM stdin;
1	1	Carlos Mendoza	produccion@famep.com	$2y$12$TJKkUtItytXPBb9rGUWP9uFWiNJxwHfpxILVreDhSFJ6SdXnmJcIS	t	2026-09-02 02:54:04.784556	\N
2	2	Ana Laura Torres	calidad@famep.com	$2y$12$oJ04AIK35SEJn/ArkoRbue7US5FUkU33kDHsPf5fyT1lqb/lW420e	t	2026-09-02 02:54:05.026063	\N
12	3	Prueba clientes	clientemboxgy@famep.com	$2y$12$68FPY63R6f.UjX3Vec6Kf.eXgFRMe34b12nE/QnKaClLQkNsyTOkW	t	2026-10-06 18:23:44.043596	FAMEP-ZOKE
\.


--
-- Name: cat_estatus_fase_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.cat_estatus_fase_id_seq', 5, true);


--
-- Name: cat_estatus_pieza_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.cat_estatus_pieza_id_seq', 5, true);


--
-- Name: cat_fases_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.cat_fases_id_seq', 3, true);


--
-- Name: cat_roles_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.cat_roles_id_seq', 2, true);


--
-- Name: cat_tipos_estructura_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.cat_tipos_estructura_id_seq', 3, true);


--
-- Name: evidencias_fotograficas_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.evidencias_fotograficas_id_seq', 6, true);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.failed_jobs_id_seq', 1, false);


--
-- Name: fases_pieza_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.fases_pieza_id_seq', 43, true);


--
-- Name: jobs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.jobs_id_seq', 1, false);


--
-- Name: logs_auditoria_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.logs_auditoria_id_seq', 1, false);


--
-- Name: obras_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.obras_id_seq', 8, true);


--
-- Name: observaciones_calidad_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.observaciones_calidad_id_seq', 105, true);


--
-- Name: operarios_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.operarios_id_seq', 10, true);


--
-- Name: piezas_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.piezas_id_seq', 20, true);


--
-- Name: usuarios_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.usuarios_id_seq', 12, true);


--
-- Name: cache_locks cache_locks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache_locks
    ADD CONSTRAINT cache_locks_pkey PRIMARY KEY (key);


--
-- Name: cache cache_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (key);


--
-- Name: cat_estatus_fase cat_estatus_fase_nombre_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cat_estatus_fase
    ADD CONSTRAINT cat_estatus_fase_nombre_key UNIQUE (nombre);


--
-- Name: cat_estatus_fase cat_estatus_fase_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cat_estatus_fase
    ADD CONSTRAINT cat_estatus_fase_pkey PRIMARY KEY (id);


--
-- Name: cat_estatus_pieza cat_estatus_pieza_nombre_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cat_estatus_pieza
    ADD CONSTRAINT cat_estatus_pieza_nombre_key UNIQUE (nombre);


--
-- Name: cat_estatus_pieza cat_estatus_pieza_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cat_estatus_pieza
    ADD CONSTRAINT cat_estatus_pieza_pkey PRIMARY KEY (id);


--
-- Name: cat_fases cat_fases_nombre_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cat_fases
    ADD CONSTRAINT cat_fases_nombre_key UNIQUE (nombre);


--
-- Name: cat_fases cat_fases_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cat_fases
    ADD CONSTRAINT cat_fases_pkey PRIMARY KEY (id);


--
-- Name: cat_roles cat_roles_nombre_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cat_roles
    ADD CONSTRAINT cat_roles_nombre_key UNIQUE (nombre);


--
-- Name: cat_roles cat_roles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cat_roles
    ADD CONSTRAINT cat_roles_pkey PRIMARY KEY (id);


--
-- Name: cat_tipos_estructura cat_tipos_estructura_nombre_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cat_tipos_estructura
    ADD CONSTRAINT cat_tipos_estructura_nombre_key UNIQUE (nombre);


--
-- Name: cat_tipos_estructura cat_tipos_estructura_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cat_tipos_estructura
    ADD CONSTRAINT cat_tipos_estructura_pkey PRIMARY KEY (id);


--
-- Name: evidencias_fotograficas evidencias_fotograficas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.evidencias_fotograficas
    ADD CONSTRAINT evidencias_fotograficas_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_uuid_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_key UNIQUE (uuid);


--
-- Name: fases_pieza fases_pieza_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fases_pieza
    ADD CONSTRAINT fases_pieza_pkey PRIMARY KEY (id);


--
-- Name: job_batches job_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_batches
    ADD CONSTRAINT job_batches_pkey PRIMARY KEY (id);


--
-- Name: jobs jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);


--
-- Name: logs_auditoria logs_auditoria_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.logs_auditoria
    ADD CONSTRAINT logs_auditoria_pkey PRIMARY KEY (id);


--
-- Name: obras obras_codigo_obra_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.obras
    ADD CONSTRAINT obras_codigo_obra_key UNIQUE (codigo_obra);


--
-- Name: obras obras_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.obras
    ADD CONSTRAINT obras_pkey PRIMARY KEY (id);


--
-- Name: observaciones_calidad observaciones_calidad_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.observaciones_calidad
    ADD CONSTRAINT observaciones_calidad_pkey PRIMARY KEY (id);


--
-- Name: operario_fase operario_fase_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.operario_fase
    ADD CONSTRAINT operario_fase_pkey PRIMARY KEY (id);


--
-- Name: operarios operarios_clave_empleado_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.operarios
    ADD CONSTRAINT operarios_clave_empleado_key UNIQUE (clave_empleado);


--
-- Name: operarios operarios_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.operarios
    ADD CONSTRAINT operarios_pkey PRIMARY KEY (id);


--
-- Name: piezas piezas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.piezas
    ADD CONSTRAINT piezas_pkey PRIMARY KEY (id);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: piezas uq_obra_marca; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.piezas
    ADD CONSTRAINT uq_obra_marca UNIQUE (obra_id, clave_marca_pieza);


--
-- Name: usuarios usuarios_email_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT usuarios_email_key UNIQUE (email);


--
-- Name: usuarios usuarios_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT usuarios_pkey PRIMARY KEY (id);


--
-- Name: fases_pieza_estatus_fase_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX fases_pieza_estatus_fase_id_index ON public.fases_pieza USING btree (estatus_fase_id);


--
-- Name: fases_pieza_pieza_id_fase_id_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX fases_pieza_pieza_id_fase_id_unique ON public.fases_pieza USING btree (pieza_id, fase_id);


--
-- Name: operario_fase_operario_id_fase_id_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX operario_fase_operario_id_fase_id_unique ON public.operario_fase USING btree (operario_id, fase_id);


--
-- Name: jobs_queue_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);


--
-- Name: sessions_last_activity_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity);


--
-- Name: sessions_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id);


--
-- Name: usuarios_codigo_acceso_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX usuarios_codigo_acceso_unique ON public.usuarios USING btree (codigo_acceso) WHERE (codigo_acceso IS NOT NULL);


--
-- Name: evidencias_fotograficas evidencias_fotograficas_observacion_calidad_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.evidencias_fotograficas
    ADD CONSTRAINT evidencias_fotograficas_observacion_calidad_id_fkey FOREIGN KEY (observacion_calidad_id) REFERENCES public.observaciones_calidad(id) ON DELETE CASCADE;


--
-- Name: fases_pieza fases_pieza_estatus_fase_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fases_pieza
    ADD CONSTRAINT fases_pieza_estatus_fase_id_fkey FOREIGN KEY (estatus_fase_id) REFERENCES public.cat_estatus_fase(id);


--
-- Name: fases_pieza fases_pieza_fase_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fases_pieza
    ADD CONSTRAINT fases_pieza_fase_id_fkey FOREIGN KEY (fase_id) REFERENCES public.cat_fases(id);


--
-- Name: fases_pieza fases_pieza_operario_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fases_pieza
    ADD CONSTRAINT fases_pieza_operario_id_fkey FOREIGN KEY (operario_id) REFERENCES public.operarios(id);


--
-- Name: operario_fase operario_fase_fase_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.operario_fase
    ADD CONSTRAINT operario_fase_fase_id_fkey FOREIGN KEY (fase_id) REFERENCES public.cat_fases(id) ON DELETE CASCADE;


--
-- Name: operario_fase operario_fase_operario_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.operario_fase
    ADD CONSTRAINT operario_fase_operario_id_fkey FOREIGN KEY (operario_id) REFERENCES public.operarios(id) ON DELETE CASCADE;


--
-- Name: fases_pieza fases_pieza_pieza_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fases_pieza
    ADD CONSTRAINT fases_pieza_pieza_id_fkey FOREIGN KEY (pieza_id) REFERENCES public.piezas(id) ON DELETE CASCADE;


--
-- Name: fases_pieza fases_pieza_usuario_captura_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fases_pieza
    ADD CONSTRAINT fases_pieza_usuario_captura_id_fkey FOREIGN KEY (usuario_captura_id) REFERENCES public.usuarios(id);


--
-- Name: evidencias_fotograficas fk_evidencias_fase_pieza; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.evidencias_fotograficas
    ADD CONSTRAINT fk_evidencias_fase_pieza FOREIGN KEY (fase_pieza_id) REFERENCES public.fases_pieza(id) ON DELETE CASCADE;


--
-- Name: logs_auditoria logs_auditoria_usuario_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.logs_auditoria
    ADD CONSTRAINT logs_auditoria_usuario_id_fkey FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id);


--
-- Name: obras obras_cliente_usuario_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.obras
    ADD CONSTRAINT obras_cliente_usuario_id_fkey FOREIGN KEY (cliente_usuario_id) REFERENCES public.usuarios(id);


--
-- Name: observaciones_calidad observaciones_calidad_fase_pieza_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.observaciones_calidad
    ADD CONSTRAINT observaciones_calidad_fase_pieza_id_fkey FOREIGN KEY (fase_pieza_id) REFERENCES public.fases_pieza(id) ON DELETE CASCADE;


--
-- Name: observaciones_calidad observaciones_calidad_usuario_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.observaciones_calidad
    ADD CONSTRAINT observaciones_calidad_usuario_id_fkey FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id);


--
-- Name: observaciones_calidad observaciones_calidad_editado_por_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.observaciones_calidad
    ADD CONSTRAINT observaciones_calidad_editado_por_fkey FOREIGN KEY (editado_por) REFERENCES public.usuarios(id);


--
-- Name: piezas piezas_estatus_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.piezas
    ADD CONSTRAINT piezas_estatus_id_fkey FOREIGN KEY (estatus_id) REFERENCES public.cat_estatus_pieza(id);


--
-- Name: piezas piezas_obra_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.piezas
    ADD CONSTRAINT piezas_obra_id_fkey FOREIGN KEY (obra_id) REFERENCES public.obras(id) ON DELETE CASCADE;


--
-- Name: piezas piezas_tipo_estructura_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.piezas
    ADD CONSTRAINT piezas_tipo_estructura_id_fkey FOREIGN KEY (tipo_estructura_id) REFERENCES public.cat_tipos_estructura(id);


--
-- Name: usuarios usuarios_rol_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT usuarios_rol_id_fkey FOREIGN KEY (rol_id) REFERENCES public.cat_roles(id);


--
-- PostgreSQL database dump complete
--

\unrestrict 0qQFha0fNtfs6YjNPxNe2KcqJQInYBO8ymaiRQSqWXiozCFeeE8E40Le94ZlB6Z

