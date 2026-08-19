import { Schema, model, models } from "mongoose";
const UserSchema=new Schema({name:{type:String,required:true},email:{type:String,required:true,unique:true,lowercase:true},passwordHash:{type:String,required:true},role:{type:String,enum:["ADMIN","OPERATOR","SUPERVISOR","DEPARTMENT_OFFICER"],required:true},department:{type:Schema.Types.ObjectId,ref:"Department"},active:{type:Boolean,default:true}},{timestamps:true});
export default models.User||model("User",UserSchema);
