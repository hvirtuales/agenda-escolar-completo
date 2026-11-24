<?php

namespace App\Controllers\Settings;

use App\Controllers\BaseController;
use App\Models\InstitutionModel;

/** Ajustes › Institución: datos, identidad visual y preferencias regionales (documento maestro 4.1). */
class InstitutionController extends BaseController
{
    public function edit()
    {
        return view('settings/institution', $this->layoutData() + [
            'title'     => 'Institución',
            'section'   => 'institucion',
            'campuses'  => service('academic')->campuses(),
            'canEdit'   => service('tenant')->can('branding.manage'),
        ]);
    }

    public function update()
    {
        $form = $this->postFields(['name', 'tax_id', 'dane_code', 'city', 'phone', 'email', 'accent_color', 'timezone', 'locale']);
        if (! $this->validate([
            'name'         => ['label' => 'Nombre', 'rules' => 'required|max_length[160]'],
            'tax_id'       => ['label' => 'NIT', 'rules' => 'permit_empty|max_length[40]'],
            'dane_code'    => ['label' => 'Código DANE', 'rules' => 'permit_empty|max_length[20]'],
            'city'         => ['label' => 'Ciudad', 'rules' => 'permit_empty|max_length[80]'],
            'phone'        => ['label' => 'Teléfono', 'rules' => 'permit_empty|max_length[40]'],
            'email'        => ['label' => 'Correo', 'rules' => 'permit_empty|valid_email|max_length[160]'],
            'accent_color' => ['label' => 'Color de acento', 'rules' => 'required|regex_match[/^#[0-9a-fA-F]{6}$/]'],
            'timezone'     => ['label' => 'Zona horaria', 'rules' => 'required|in_list[' . implode(',', array_keys(\App\Models\InstitutionModel::TIMEZONES)) . ']'],
            'locale'       => ['label' => 'Idioma', 'rules' => 'required|in_list[es,en]'],
        ])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        $form['accent_color'] = strtoupper($form['accent_color']);
        foreach (['tax_id', 'dane_code', 'city', 'phone', 'email'] as $k) {
            $form[$k] = $form[$k] ?: null;
        }

        $id = (int) service('tenant')->institutionId();
        model(InstitutionModel::class)->update($id, $form);
        service('audit')->log('institution.updated', ['campos' => array_keys($form)]);

        return redirect()->to('ajustes/institucion')->with('toast', ['type' => 'ok', 'msg' => 'Datos de la institución guardados.']);
    }
}
