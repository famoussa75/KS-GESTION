@extends('layouts.public')
@section('title',$proforma->number)
@section('content') @include('sales._document',['sale'=>$proforma]) @endsection
