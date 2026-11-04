<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Intervention\Image\ImageManagerStatic as Image;
use Illuminate\Support\Facades\Storage;

class ReleaseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return session('userType') == 'Admin';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => 'required',
            'body' => 'required',
        ];
    }

    protected function getValidatorInstance()
    {
        $validator = parent::getValidatorInstance();
        $validator->after(function ($validator) {
            $data = $this->all();
            if (isset($data['id']) && !empty($data['id'])) {
                $data['operation_by'] = Auth::id();
                $data['operation_by_user_type'] = session('userType');
                $data['operation_at'] = date('Y-m-d H:i:s');

                if (isset($data['image_i'])) {
                    $image = $this->addNewPhotos($data['image_i']);
                    $data['image'] = $image;
                }
            } else {
                $data['operation_by'] = Auth::id();
                $data['operation_by_user_type'] = session('userType');
                $data['operation_at'] = date('Y-m-d H:i:s');

                if (isset($data['image_i'])) {
                    $image = $this->addNewPhotos($data['image_i']);
                    $data['image'] = $image;
                }
            }

            $this->getInputSource()->replace($data);
        });
        return $validator;
    }

    private function addNewPhotos($photo)
    {
        $name = time() . $photo->getClientOriginalName();
        $imageThumb = Image::make($photo->path())->stream();
        $filePath = "images/release/" . $name;
        Storage::disk('s3')->put($filePath, $imageThumb);
        $url = Storage::disk('s3')->url($filePath);
        return $url;
    }
}
