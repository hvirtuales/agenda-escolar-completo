<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 *
 * Extend this class in any new controllers:
 * ```
 *     class Home extends BaseController
 * ```
 *
 * For security, be sure to declare any new methods as protected or private.
 */
abstract class BaseController extends Controller
{
    /**
     * Be sure to declare properties for any property fetch you initialized.
     * The creation of dynamic property is deprecated in PHP 8.2.
     */

    // protected $session;

    /**
     * @param CLIRequest|IncomingRequest $request
     *
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Load here all helpers you want to be available in your controllers that extend BaseController.
        // Caution: Do not put the this below the parent::initController() call below.
        $this->helpers = ['form', 'url', 'ui', 'csv'];

        // Caution: Do not edit this line.
        parent::initController($request, $response, $logger);

        // Preload any models, libraries, etc, here.
        // $this->session = service('session');
    }

    /** Vuelve atrás con un toast. Tipos: ok, error, info. */
    protected function backWith(string $type, string $msg)
    {
        return redirect()->back()->with('toast', ['type' => $type, 'msg' => $msg]);
    }

    /**
     * Vuelve atrás tras un error de validación y reabre el Drawer con lo escrito.
     * $form son los valores que la vista vuelve a poner en el formulario (nunca contraseñas).
     */
    protected function backInvalid(string $mode, array $form, ?array $errors = null)
    {
        return redirect()->back()
            ->with('errors', $errors ?? $this->validator->getErrors())
            ->with('reopen', ['mode' => $mode, 'form' => $form]);
    }

    /** Campos permitidos del POST, recortados. */
    protected function postFields(array $fields): array
    {
        $out = [];
        foreach ($fields as $f) {
            $v       = $this->request->getPost($f);
            $out[$f] = is_string($v) ? trim($v) : ($v ?? '');
        }

        return $out;
    }

    /**
     * Layout de las pantallas del estudiante (barra lateral propia). Si quien navega no es estudiante, es la «vista de
     * estudiante» de un grupo real (service('studentPreview')): la barra de vista previa permite cambiar de grupo.
     */
    protected function studentLayout(): array
    {
        $ctx  = $this->layoutData();
        $base = ['railItems' => config('AgendaEscolar')->studentRail, 'quota' => service('aiQuota')->balance()];
        $p    = service('studentPreview');
        if ($p->isStudent()) {
            $group = service('academic')->studentGroup((int) service('auth')->id());
            $ctx['user'] += ['group' => $group['name'] ?? 'sin grupo'];

            return $base + ['studentPreview' => false] + $ctx;
        }
        $g           = $p->group();
        $ctx['user'] = ['group' => $g['name'] ?? 'sin grupo'] + $ctx['user'];

        return $base + ['studentPreview' => true, 'previewGroups' => $p->groups(), 'previewGroup' => $g, 'institutions' => []] + $ctx;
    }

    /** Respuesta de descarga CSV (formato único: csv_helper). */
    protected function csvResponse(string $filename, array $head, iterable $rows)
    {
        return $this->response->setHeader('Content-Type', 'text/csv; charset=utf-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setBody(csv_build($head, $rows));
    }

    /** Layout de la consola de plataforma (superadministrador sin institución activa): menú de la consola. */
    protected function platformLayout(): array
    {
        return [
            'user' => service('auth')->user(), 'isSuperadmin' => true, 'platform' => true, 'institution' => null,
            'institutions' => service('tenant')->availableInstitutions(), 'networks' => service('network')->forUser(),
            'perms' => [], 'railItems' => config('AgendaEscolar')->platformRail,
        ];
    }

    /** Datos que necesita el layout: persona, institución activa (con su periodo en curso), otras instituciones y permisos. */
    protected function layoutData(): array
    {
        $auth   = service('auth');
        $tenant = service('tenant');
        $inst   = $tenant->institution();
        $period = $inst ? service('academic')->currentPeriod() : null;
        if ($inst) {
            service('notify')->sweep(); // avisos por fecha (vence pronto / venció), como máximo cada 30 min
        }

        return [
            'user'         => $auth->user(),
            'isSuperadmin' => $auth->isSuperadmin(),
            'institution'  => $inst ? $inst + ['period' => $period['name'] ?? ''] : null,
            'institutions' => $tenant->availableInstitutions(),
            'perms'        => $tenant->permissions(),
            'networks'     => service('network')->forUser(),
        ] + ($tenant->isGuardian() ? ['railItems' => $this->guardianRail()] : ($tenant->isStudent() ? ['railItems' => config('AgendaEscolar')->studentRail] : []));
    }

    /** Menú del acudiente: «Mi estudiante» en singular cuando solo tiene uno vinculado. */
    private function guardianRail(): array
    {
        $rail = config('AgendaEscolar')->guardianRail;
        if (count(service('guardians')->studentsOf((int) service('auth')->id())) === 1) {
            $rail[0] = ['label' => 'Mi estudiante', 'nav' => 'acudiente1'] + $rail[0];
        }

        return $rail;
    }
}
