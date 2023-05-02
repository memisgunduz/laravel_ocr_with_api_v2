<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\GetData;
use App\Models\Rules;
use App\Jobs\OcrJob;
use Spatie\PdfToImage\Pdf;
use App\Models\OcrJobBlock;
use Illuminate\Support\Str;
use App\Http\Requests\OCRRequest;
use Illuminate\Support\Facades\File;
use thiagoalessio\TesseractOCR\TesseractOCR;

class OcrController extends Controller
{

    public function dispatchJob(OCRRequest $request){

        $files = $request->file('files');
        $article = null;
        $fileFound= null;
        $text = null;
        foreach ($files as $file) {
            $fileType = $file->extension();
            $fileName = Str::random(32) . '.' . $fileType;
            $filePath = $file->storeAs($this->getFolderName($fileType), $fileName, '');
            $control = $this->ocrProgress($fileName, $filePath, $fileType, $request->id);

            $fileFound = $file->getClientOriginalName();
            if ($control->getData()->success) {
                $text = json_encode($control->getData()->message);
                $rules = Rules::pluck('rule');

                foreach ($rules as $rule) {
                    if (strpos(strtolower($text), strtolower($rule)) !== false) {
                        $article = $rule;
                    }
                }

            } else {
                return response()->json([
                    "success" => false,
                    "error"=>true,
                    "message" => "Error! The uploaded file content could not be read.",
                ]);
            }
        }

        if($article){
            return response()->json([
                "success" => false,
                "fileName"=> $fileFound,
                "message" => "Matching item found",
                "article" => $article
            ]);
        }else{
            return response()->json([
                "success" => true,
                //"text"=>strtolower($text),
                "message" => "Nothing matched"
            ]);
        }

    }


    function ocrProgress($fileName,$filePath,$extension){
        $result = $this->ocr($fileName, $filePath, $extension);
        $fileType=$extension;
        if($result->getData()->success){
            $this->deleteFile(public_path($this->getFolderName($fileType)  . $fileName));
            return response()->json([
                'success' => true,
                'message'=>$result->getData()->text
            ]);
        }else{
            $this->deleteFile(public_path($this->getFolderName($fileType)  . $fileName));
            return response()->json([
                'success' => false,
                'message'=>'error'
            ]);
        }
    }

    public function getFolderName($extension) {
        $fileType=$extension;
        if($fileType=="pdf"){
            $fileFolder="PDF/";
        }else if($fileType=="docx"){
            $fileFolder="DOCX/";
        }else{
            $fileFolder="IMG/";
        }
        return $fileFolder;
    }

    public function pdfToText($getFileName, $type = "PDF",$type2)
    {
        try {
            if($type2=="docx"){
                $pdf = new Pdf(public_path('PDF/' . $getFileName.$type));
            }else{
                $pdf = new Pdf(public_path('PDF/' . $getFileName));
            }
            $numberOfpages = $pdf->getNumberOfPages();
            $result = [];

            for ($i = 1; $i <= $numberOfpages; $i++) {
                $filename = $i . "_" . $getFileName . ".jpg";
                $pdf->setPage($i)->saveImage(public_path('IMG/' . $filename));

                $ocr = new TesseractOCR();
                $ocr->image(public_path('IMG/' . $filename));

                $result[] = $ocr->run();
                $this->deleteFile(public_path('IMG/'  . $filename));
            }

            $this->deleteFile(public_path('PDF/'  . $getFileName.$type));
            return response()->json([
                'success' => true,
                'type' => $type,
                'text' => $result,
                'pages' => $result
            ]);
        } catch (Exception $ex) {
            $this->deleteFile(public_path('PDF/'  . $getFileName.$type));
            return response()->json([
                'success' => false,
                'message'=> (string)$ex
            ]);
        }
    }

    public function fileUpload(OCRRequest $request)
    {
        $fileName = Str::random(32);
        $filePath = $request->file('file')->storeAs('PDF', $fileName, '');
        return $this->ocr($fileName, $filePath, $request->file('file')->extension(), $request->id);
    }

    function deleteFile($filePath){
        if (File::exists($filePath)) File::delete($filePath);
    }

    public function ocr($fileName, $filePath, $extension) {
        $text = "";
        if ($extension == "pdf") {
            return $this->pdfToText($fileName,".pdf","pdf");
        }
        if ($extension == "docx") {
            try {
                $domPdfPath = base_path('vendor/dompdf/dompdf');
                \PhpOffice\PhpWord\Settings::setPdfRendererPath($domPdfPath);
                \PhpOffice\PhpWord\Settings::setPdfRendererName('DomPDF');
                $Content = \PhpOffice\PhpWord\IOFactory::load(public_path('DOCX/' . $fileName));
                $PDFWriter = \PhpOffice\PhpWord\IOFactory::createWriter($Content, 'PDF');
                $PDFWriter->save(public_path('PDF/' . $fileName . '.pdf'));
                $this->deleteFile(public_path('DOCX/'  . $fileName));
            } catch (\Throwable $th) {
                return response()->json([
                    'success' => false,
                    'message'=>$th
                ]);
            }
            return $this->pdfToText($fileName, ".pdf","docx");
        } else {
            try {
                $ocr = new TesseractOCR();
                $ocr->image(public_path($filePath));
                $text = $ocr->run();
                if (File::exists(public_path($filePath))) {
                    File::delete(public_path($filePath));
                }
                return response()->json([
                    'success' => true,
                    'type' => $extension,
                    'text' => $text,
                ]);
            } catch (\Throwable $th) {
                return response()->json([
                    'success' => false,
                    'message'=>$th
                ]);
            }
        }
    }
}
