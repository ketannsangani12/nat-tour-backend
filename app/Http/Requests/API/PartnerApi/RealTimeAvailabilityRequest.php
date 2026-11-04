<?php

namespace App\Http\Requests\API\PartnerApi;

use App\Http\Requests\API\Core\FormRequest;

use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Contracts\Validation\Validator;

class RealTimeAvailabilityRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function rules(): array
    {
        return [
            'data.ApiKey' => 'required',
            'data.ResellerId' => 'required',
            'data.SupplierId' => 'required',
            'data.StartDate' => 'required',
            'data.ProductCode' => 'required'
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function messages(): array
    {
        return [
        ];
    }

    /**
     * Handle a invalid/valid json request validation attempt and pass response.
     *
     * @return void
     *
     */
    protected function failedValidation(Validator $validator)
    {
        $responseJson = [
            "responseType" => "AvailabilityResponse",
            "RequestStatus" => [
                "Status" => "ERROR"
            ]
        ];
        if(empty($this->all())){
            $responseJson['RequestStatus']['Error']['ErrorCode'] = "422";
            $responseJson['RequestStatus']['Error']['ErrorMessage'] = "Your JSON request is not valid, please enter a valid JSON request.";
            $responseJson['RequestStatus']['Error']['ErrorDetails'] = "Invalid data; i.e., data is in incorrect format, non-parsable or otherwise invalid";
            throw new HttpResponseException(
                response()->json($responseJson, Response::HTTP_UNPROCESSABLE_ENTITY)
            );
        }else{
            $responseJson['RequestStatus']['Error']['ErrorCode'] = "400";
            if(!isset($this->data['ApiKey'])){
                $responseJson['RequestStatus']['Error']['ErrorMessage'] = "ApiKey is missing.";
            }
            if(!isset($this->data['ResellerId'])){
                $responseJson['RequestStatus']['Error']['ErrorMessage'] = "ResellerId is missing.";
            }
            if(!isset($this->data['SupplierId'])){
                $responseJson['RequestStatus']['Error']['ErrorMessage'] = "SupplierId is missing.";
            }
            if(!isset($this->data['StartDate'])){
                $responseJson['RequestStatus']['Error']['ErrorMessage'] = "StartDate is missing.";
            }
            if(!isset($this->data['ProductCode'])){
                $responseJson['RequestStatus']['Error']['ErrorMessage'] = "ProductCode is missing.";
            }
            $responseJson['RequestStatus']['Error']['ErrorDetails'] = "Field validation error";
            throw new HttpResponseException(response($responseJson, Response::HTTP_UNPROCESSABLE_ENTITY));
        }
    }
}
