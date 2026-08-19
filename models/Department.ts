import { Schema, model, models } from "mongoose";
const DepartmentSchema=new Schema({name:{type:String,required:true,unique:true},code:{type:String,required:true,unique:true},contact:String,active:{type:Boolean,default:true}},{timestamps:true});
export default models.Department||model("Department",DepartmentSchema);
