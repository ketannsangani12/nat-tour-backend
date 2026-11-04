<?php

namespace App\Http\Requests\API\TripAdvisor;

use App\Http\Requests\API\Core\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Contracts\Validation\Validator;

class CreateBookingRequest extends FormRequest
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
            'data.Timestamp' => 'required',
            'data.BookingReference' => 'required',
            'data.TravelDate' => 'required',
            'data.SupplierProductCode' => 'required',
            'data.Traveller' => 'required|array|min:1',
            'data.Traveller.*.TravellerIdentifier' => 'required',
            'data.Traveller.*.GivenName' => 'required',
            'data.Traveller.*.Surname' => 'required',
            'data.Traveller.*.AgeBand' => 'required',
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
            "responseType" => "BookingResponse",
            "RequestStatus" => [
                "Status" => "ERROR"
            ]
        ];
        if(empty($this->all())){
            $responseJson['RequestStatus']['Error']['ErrorCode'] = "TGDS0023";
            $responseJson['RequestStatus']['Error']['ErrorMessage'] = "Your JSON request is not valid, please enter a valid JSON request.";
            $responseJson['RequestStatus']['Error']['ErrorDetails'] = "Invalid data; i.e., data is in incorrect format, non-parsable or otherwise invalid";
            throw new HttpResponseException(
                response()->json($responseJson, Response::HTTP_UNPROCESSABLE_ENTITY)
            );
        }else{
            $responseJson['RequestStatus']['Error']['ErrorCode'] = "TGDS0020";
            if(!isset($this->data['ApiKey'])){
                $responseJson['RequestStatus']['Error']['ErrorMessage'] = "ApiKey is missing.";
            }
            if(!isset($this->data['ResellerId'])){
                $responseJson['RequestStatus']['Error']['ErrorMessage'] = "ResellerId is missing.";
            }
            if(!isset($this->data['SupplierId'])){
                $responseJson['RequestStatus']['Error']['ErrorMessage'] = "SupplierId is missing.";
            }
            if(!isset($this->data['Timestamp'])){
                $responseJson['RequestStatus']['Error']['ErrorMessage'] = "Timestamp is missing.";
            }
            if(!isset($this->data['BookingReference'])){
                $responseJson['RequestStatus']['Error']['ErrorMessage'] = "BookingReference is missing.";
            }
            if(!isset($this->data['TravelDate'])){
                $responseJson['RequestStatus']['Error']['ErrorMessage'] = "TravelDate is missing.";
            }
            if(!isset($this->data['SupplierProductCode'])){
                $responseJson['RequestStatus']['Error']['ErrorMessage'] = "SupplierProductCode is missing.";
            }
            if(!isset($this->data['Traveller'])){
                $responseJson['RequestStatus']['Error']['ErrorMessage'] = "Traveller is missing, Atleast One Traveller is required.";
            }
            if(isset($this->data['Traveller'])){
                foreach ($this->data['Traveller'] as $key => $value) {
                    if(!isset($value['TravellerIdentifier'])){
                        $responseJson['RequestStatus']['Error']['ErrorMessage'] = "Traveller's TravellerIdentifier is missing.";
                    }
                    if(!isset($value['GivenName'])){
                        $responseJson['RequestStatus']['Error']['ErrorMessage'] = "Traveller's GivenName is missing.";
                    }
                    if(!isset($value['Surname'])){
                        $responseJson['RequestStatus']['Error']['ErrorMessage'] = "Traveller's Surname is missing.";
                    }
                    if(!isset($value['AgeBand'])){
                        $responseJson['RequestStatus']['Error']['ErrorMessage'] = "Traveller's AgeBand is missing.";
                    }
                }
            }
            $responseJson['RequestStatus']['Error']['ErrorDetails'] = "Field validation error";
            throw new HttpResponseException(response($responseJson, Response::HTTP_UNPROCESSABLE_ENTITY));
        }
    }
}