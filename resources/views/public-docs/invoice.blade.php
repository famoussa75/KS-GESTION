@extends('layouts.public')
@section('title',$invoice->number)
@section('content') @include('invoices._document',['invoice'=>$invoice]) @endsection
