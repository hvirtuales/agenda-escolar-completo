<?php

namespace App\Controllers;

/**
 * Página pública (landing) y solicitud de demostración. Quien ya tiene sesión va directo a su inicio.
 */
class LandingController extends BaseController
{
    public const ROLES    = ['Rectoría o dirección', 'Coordinación académica', 'Docente', 'Tecnología / sistemas', 'Red o grupo de colegios', 'Otro'];
    public const STUDENTS = ['Menos de 300', '300 a 1.000', '1.000 a 3.000', 'Más de 3.000'];

    public function index()
    {
        if (service('auth')->check()) {
            return redirect()->to(service('auth')->isSuperadmin() ? 'plataforma' : 'inicio');
        }

        return view('landing/index', ['title' => 'Plataforma educativa con IA', 'roles' => self::ROLES, 'sizes' => self::STUDENTS]);
    }

    /** Guarda la solicitud y avisa a los superadministradores. Máximo 3 por hora por IP; campo trampa contra bots. */
    public function request()
    {
        $back = static fn () => redirect()->to(site_url('/') . '#demo');
        if ((string) $this->request->getPost('website') !== '') { // los bots llenan el campo oculto
            return $back()->with('demoSent', true);
        }
        if (service('throttler')->check('demo-' . md5($this->request->getIPAddress()), 3, HOUR) === false) {
            return $back()->withInput()->with('toast', ['type' => 'error', 'msg' => 'Recibimos varias solicitudes desde tu conexión. Intenta de nuevo en una hora.']);
        }

        $in = $this->postFields(['name', 'email', 'phone', 'institution', 'role', 'city', 'students', 'message']);
        $v  = service('validation');
        $v->reset();
        if (! $v->setRules([
            'name'        => ['label' => 'nombre', 'rules' => 'required|max_length[120]'],
            'email'       => ['label' => 'correo', 'rules' => 'required|valid_email|max_length[160]'],
            'phone'       => ['label' => 'teléfono', 'rules' => 'permit_empty|max_length[40]|regex_match[/^[0-9 +()-]+$/]'],
            'institution' => ['label' => 'institución', 'rules' => 'required|max_length[160]'],
            'role'        => ['label' => 'cargo', 'rules' => 'permit_empty|in_list[' . implode(',', self::ROLES) . ']'],
            'city'        => ['label' => 'ciudad', 'rules' => 'permit_empty|max_length[80]'],
            'students'    => ['label' => 'estudiantes', 'rules' => 'permit_empty|in_list[' . implode(',', self::STUDENTS) . ']'],
            'message'     => ['label' => 'mensaje', 'rules' => 'permit_empty|max_length[1000]'],
        ], [
            'name'        => ['required' => 'Escribe tu nombre.'],
            'email'       => ['required' => 'Escribe tu correo.', 'valid_email' => 'Revisa el correo: no parece válido.'],
            'phone'       => ['regex_match' => 'El teléfono solo puede tener números, espacios y + ( ) -.'],
            'institution' => ['required' => 'Escribe el nombre de tu institución.'],
        ])->run($in)) {
            return $back()->withInput()->with('errors', $v->getErrors());
        }

        $db = db_connect();
        // Las claves de la izquierda ganan: el correo se guarda normalizado en minúsculas.
        $db->table('demo_requests')->insert([
            'email' => mb_strtolower($in['email']), 'status' => 'new', 'ip_address' => $this->request->getIPAddress(), 'created_at' => date('Y-m-d H:i:s'),
        ] + array_map(static fn ($x) => $x === '' ? null : $x, $in));
        $supers = array_column($db->table('users')->select('id')->where('is_superadmin', 1)->where('status', 'active')->get()->getResultArray(), 'id');
        service('notify')->send($supers, 'platform', 'Solicitud de demostración: ' . $in['institution'],
            $in['name'] . ' · ' . mb_strtolower($in['email']) . ($in['city'] !== '' ? ' · ' . $in['city'] : ''), 'plataforma/solicitudes', null, null);

        return $back()->with('demoSent', true);
    }
}
