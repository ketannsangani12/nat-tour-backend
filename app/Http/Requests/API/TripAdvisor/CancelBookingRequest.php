<?php

namespace App\Http\Requests\API\TripAdvisor;

use App\Http\Requests\API\Core\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Contracts\Validation\Validator;

class CancelBookingRequest extends FormRequest
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
            'data.SupplierConfirmationNumber' => 'required',
            'data.CancelDate' => 'required',
            'data.Author' => 'required',
            'data.Reason' => 'required'
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
            "responseType" => "BookingCancellationResponse",
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
            if(!isset($this->data['SupplierConfirmationNumber'])){
                $responseJson['RequestStatus']['Error']['ErrorMessage'] = "SupplierConfirmationNumber is missing.";
            }
            if(!isset($this->data['CancelDate'])){
                $responseJson['RequestStatus']['Error']['ErrorMessage'] = "CancelDate is missing.";
            }
            if(!isset($this->data['Author'])){
                $responseJson['RequestStatus']['Error']['ErrorMessage'] = "Author(The person who's going to cancel booking) is missing.";
            }
            if(!isset($this->data['Reason'])){
                $responseJson['RequestStatus']['Error']['ErrorMessage'] = "Reason is missing.";
            }
            $responseJson['RequestStatus']['Error']['ErrorDetails'] = "Field validation error";
            throw new HttpResponseException(response($responseJson, Response::HTTP_UNPROCESSABLE_ENTITY));
        }
    }
}